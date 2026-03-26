<?php

namespace App\Jobs;

use App\Models\EmailBatch;
use App\Models\EmailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailRecipientJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $recipientId;

    public int $tries = 1;

    public function __construct(int $recipientId)
    {
        $this->recipientId = $recipientId;
    }

    public function handle(): void
    {
        $recipient = EmailRecipient::query()->with('batch')->findOrFail($this->recipientId);
        $batch = $recipient->batch;

        // Respect opt-outs immediately.
        if ($recipient->unsubscribed_at !== null) {
            return;
        }

        // Avoid duplicate sends when a job is retried or dispatched twice.
        if ($recipient->send_status === 'sent') {
            return;
        }

        $recipient->send_status = 'sending';
        $recipient->send_error = null;
        $recipient->save();

        // Update the batch high-level status while work is in progress.
        if ($batch->status !== 'completed' && $batch->status !== 'failed') {
            $batch->status = 'sending';
            $batch->save();
        }

        $publicBaseUrl = rtrim((string) env('BULKMAIL_PUBLIC_BASE_URL', 'http://localhost:8000'), '/');

        $unsubscribeUrl = $publicBaseUrl.'/unsubscribe/'.$recipient->unsubscribe_token;
        $openPixelUrl = $publicBaseUrl.'/track/open/'.$recipient->open_token;

        $trackingPixelHtml = '<img src="'.$openPixelUrl.'" width="1" height="1" style="display:none" alt="" />';
        $unsubscribeLinkHtml = '<div style="margin-top:16px;font-size:12px;color:#666;">'
            .'<a href="'.$unsubscribeUrl.'">Unsubscribe</a>'
            .'</div>';

        // `body_html` is stored as a sanitized fragment; we append tracking + unsubscribe per recipient.
        $finalHtml = rtrim($batch->body_html)."\n".$trackingPixelHtml."\n".$unsubscribeLinkHtml;

        $subject = $batch->subject_plain ?? strip_tags((string) $batch->subject_html);

        try {
            Mail::send('emails.bulk_recipient', ['body' => $finalHtml], function ($message) use ($recipient, $subject) {
                $message->to($recipient->email)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            $recipient->send_status = 'sent';
            $recipient->send_error = null;
            $recipient->save();

            $this->markBatchStatusIfComplete($batch->id);
        } catch (\Throwable $e) {
            $recipient->send_status = 'failed';
            $recipient->send_error = $e->getMessage();
            $recipient->save();

            $this->markBatchStatusIfComplete($batch->id);
        }
    }

    /**
     * Mark a batch as completed/failed once all recipients are processed.
     */
    private function markBatchStatusIfComplete(int $batchId): void
    {
        // Remaining recipients are those that are NOT unsubscribed and still not attempted.
        $hasRemaining = EmailRecipient::query()
            ->where('email_batch_id', $batchId)
            ->whereNull('unsubscribed_at')
            ->whereNull('send_status')
            ->exists();

        if ($hasRemaining) {
            return;
        }

        $hasFailures = EmailRecipient::query()
            ->where('email_batch_id', $batchId)
            ->whereNull('unsubscribed_at')
            ->where('send_status', 'failed')
            ->exists();

        $newStatus = $hasFailures ? 'failed' : 'completed';

        EmailBatch::query()
            ->where('id', $batchId)
            ->whereNotIn('status', ['completed', 'failed'])
            ->update(['status' => $newStatus]);
    }
}

