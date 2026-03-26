<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailRecipientJob;
use App\Models\Contact;
use App\Models\EmailBatch;
use App\Models\EmailRecipient;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $batches = EmailBatch::query()
            ->where('created_by', $user->id)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get(['id', 'subject_plain', 'status', 'created_at']);

        return response()->json($batches);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'bodyHtml' => ['required', 'string'],
            'recipients' => ['required', 'array'],
            'recipients.emails' => ['array'],
            'recipients.contactIds' => ['array'],
        ]);

        $rawEmails = $data['recipients']['emails'] ?? [];
        $contactIds = $data['recipients']['contactIds'] ?? [];

        $emailsFromInput = collect($rawEmails)
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))
            ->filter(fn ($e) => $e !== '')
            ->values();

        $contacts = Contact::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $contactIds)
            ->get(['email']);

        $emailsFromContacts = $contacts->pluck('email')->map(fn ($e) => mb_strtolower((string) $e));

        $allEmails = $emailsFromInput
            ->merge($emailsFromContacts)
            ->unique()
            ->values();

        $allEmails = $allEmails->filter(function (string $email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        })->values();

        if ($allEmails->isEmpty()) {
            return response()->json(['message' => 'No valid recipients found.'], 422);
        }

        $sanitizedBodyHtml = HtmlSanitizer::sanitizeComposerHtml($data['bodyHtml']);

        $batch = EmailBatch::query()->create([
            'created_by' => $user->id,
            'subject_plain' => $data['subject'],
            'subject_html' => null,
            'body_html' => $sanitizedBodyHtml,
            'status' => 'queued',
        ]);

        foreach ($allEmails as $email) {
            $unsubscribeToken = $this->generateUniqueToken(fn ($t) => EmailRecipient::query()->where('unsubscribe_token', $t)->exists());
            $openToken = $this->generateUniqueToken(fn ($t) => EmailRecipient::query()->where('open_token', $t)->exists());

            $recipient = EmailRecipient::query()->create([
                'email_batch_id' => $batch->id,
                'email' => $email,
                'unsubscribe_token' => $unsubscribeToken,
                'open_token' => $openToken,
            ]);

            SendEmailRecipientJob::dispatch($recipient->id)->onQueue('emails');
        }

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'recipients' => $allEmails->count(),
        ], 201);
    }

    public function show(Request $request, string $id)
    {
        if (! ctype_digit($id)) {
            return response()->json([
                'message' => 'Invalid batch id.',
            ], 422);
        }

        $user = $request->user();

        $batch = EmailBatch::query()
            ->where('created_by', $user->id)
            ->findOrFail((int) $id);

        $recipientsQuery = EmailRecipient::query()->where('email_batch_id', $batch->id);

        $total = (clone $recipientsQuery)->count();
        $sent = (clone $recipientsQuery)->where('send_status', 'sent')->count();
        $failed = (clone $recipientsQuery)->where('send_status', 'failed')->count();
        $sending = (clone $recipientsQuery)->where('send_status', 'sending')->count();
        $opened = (clone $recipientsQuery)->whereNotNull('opened_at')->count();
        $unsubscribed = (clone $recipientsQuery)->whereNotNull('unsubscribed_at')->count();

        return response()->json([
            'id' => $batch->id,
            'subject_plain' => $batch->subject_plain,
            'status' => $batch->status,
            'created_at' => $batch->created_at,
            'progress' => [
                'total' => $total,
                'sent' => $sent,
                'sending' => $sending,
                'failed' => $failed,
                'opened' => $opened,
                'unsubscribed' => $unsubscribed,
            ],
        ]);
    }

    /**
     * Retry sending for all failed recipients in a failed batch.
     */
    public function retry(Request $request, string $id)
    {
        if (! ctype_digit($id)) {
            return response()->json([
                'message' => 'Invalid batch id.',
            ], 422);
        }

        $user = $request->user();

        $batch = EmailBatch::query()
            ->where('created_by', $user->id)
            ->findOrFail((int) $id);

        if ($batch->status !== 'failed') {
            return response()->json([
                'message' => 'Only failed batches can be retried.',
            ], 409);
        }

        // Reset only failed recipients that haven't unsubscribed.
        $failedRecipientIds = EmailRecipient::query()
            ->where('email_batch_id', $batch->id)
            ->where('send_status', 'failed')
            ->whereNull('unsubscribed_at')
            ->pluck('id')
            ->all();

        if (empty($failedRecipientIds)) {
            // Nothing to retry; transition back to queued/completed based on recipient state.
            return response()->json([
                'message' => 'No failed recipients found to retry.',
                'retried' => 0,
            ], 200);
        }

        EmailRecipient::query()
            ->whereIn('id', $failedRecipientIds)
            ->update([
                'send_status' => null,
                'send_error' => null,
            ]);

        $batch->status = 'queued';
        $batch->save();

        foreach ($failedRecipientIds as $recipientId) {
            SendEmailRecipientJob::dispatch((int) $recipientId)->onQueue('emails');
        }

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'retried' => count($failedRecipientIds),
        ], 201);
    }

    /**
     * Fetch per-recipient logs (send status + send_error) for this batch.
     *
     * Query params:
     * - status: 'failed' | 'sent' | 'all' (default: 'failed')
     */
    public function recipients(Request $request, string $id)
    {
        if (! ctype_digit($id)) {
            return response()->json([
                'message' => 'Invalid batch id.',
            ], 422);
        }

        $user = $request->user();

        $batch = EmailBatch::query()
            ->where('created_by', $user->id)
            ->findOrFail((int) $id);

        $status = (string) $request->query('status', 'failed');
        $allowed = ['failed', 'sent', 'all'];
        if (! in_array($status, $allowed, true)) {
            return response()->json([
                'message' => 'Invalid status filter.',
            ], 422);
        }

        $query = EmailRecipient::query()
            ->where('email_batch_id', $batch->id)
            ->orderBy('id', 'desc')
            ->select([
                'id',
                'email',
                'send_status',
                'send_error',
                'unsubscribed_at',
                'opened_at',
                'created_at',
            ]);

        if ($status === 'failed') {
            $query->where('send_status', 'failed');
        } elseif ($status === 'sent') {
            $query->where('send_status', 'sent');
        }

        $recipients = $query->get();

        return response()->json([
            'batch_id' => $batch->id,
            'status' => $status,
            'items' => $recipients,
        ]);
    }

    /**
     * Generate a unique token for email recipients.
     *
     * @param  callable(string):bool  $exists
     */
    private function generateUniqueToken(callable $exists): string
    {
        do {
            $token = Str::random(64);
        } while ($exists($token));

        return $token;
    }
}

