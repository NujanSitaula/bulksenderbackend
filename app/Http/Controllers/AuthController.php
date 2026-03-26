<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Single-user API login that returns a Sanctum personal access token.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $adminUsername = (string) env('BULKMAIL_ADMIN_USERNAME', '');
        $adminPassword = (string) env('BULKMAIL_ADMIN_PASSWORD', '');
        $adminEmail = (string) env('BULKMAIL_ADMIN_EMAIL', '');
        $adminName = (string) env('BULKMAIL_ADMIN_NAME', 'Admin');

        if ($adminUsername === '' || $adminPassword === '' || $adminEmail === '') {
            return response()->json([
                'message' => 'Server is not configured for bulkmail auth.',
            ], 500);
        }

        $ok = hash_equals($adminUsername, $data['username'])
            && hash_equals($adminPassword, $data['password']);

        if (! $ok) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $user = User::firstOrCreate(
            ['email' => $adminEmail],
            ['name' => $adminName, 'password' => Hash::make($adminPassword)]
        );

        $token = $user->createToken('bulkmail')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}

