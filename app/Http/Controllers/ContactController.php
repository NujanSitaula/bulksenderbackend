<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $contacts = Contact::query()
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->get(['id', 'name', 'email']);

        return response()->json($contacts);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = mb_strtolower(trim($data['email']));

        // Keep it simple for v1: update existing contact with same email.
        $contact = Contact::query()->updateOrCreate(
            ['user_id' => $user->id, 'email' => $email],
            ['name' => $data['name']]
        );

        return response()->json($contact);
    }

    public function destroy(Request $request, string $id)
    {
        $user = $request->user();

        $contact = Contact::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $contact->delete();

        return response()->json(['ok' => true]);
    }
}

