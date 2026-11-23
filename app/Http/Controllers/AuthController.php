<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'role' => $user->role,
            'name' => $user->name,
        ]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$user || !Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'The current password is incorrect.'], 422);
        }

        abort_unless(in_array($user->role, ['admin', 'driver'], true), 403);
        // Always use the authenticated account, never a client-supplied email.
        if (config('auth.password_reset_testing.enabled') && ! in_array(
            strtolower($user->email),
            array_map(static fn ($email) => strtolower(trim($email)), (array) config('auth.password_reset_testing.whitelist', [])),
            true
        )) {
            return response()->json(['message' => 'Password reset email is unavailable for this account while email testing mode is enabled.'], 503);
        }
        $status = Password::sendResetLink(['email' => $user->email]);
        if ($status === Password::RESET_THROTTLED) {
            $retry = (int) config('auth.passwords.users.throttle', 60);
            return response()->json(['message' => 'Please wait before requesting another email.'], 429, ['Retry-After' => (string) $retry]);
        }
        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Unable to send the password reset email.'], 503);
        }
        return response()->json(['message' => 'Check your registered email to finish changing your password.']);
    }

}