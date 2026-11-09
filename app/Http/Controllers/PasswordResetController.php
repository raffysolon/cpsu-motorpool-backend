<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    private const GENERIC_MESSAGE = 'If an account exists with this email, you will receive password reset instructions.';

    private const INVALID_TOKEN_MESSAGE = 'Invalid or expired reset token.';

    public function requestReset(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user?->role === 'admin') {
            Log::warning('Blocked password reset request for admin account.', [
                'user_id' => $user->getKey(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => self::GENERIC_MESSAGE]);
        }

        if (! $user || $user->role !== 'driver') {
            return response()->json(['message' => self::GENERIC_MESSAGE]);
        }

        if (config('auth.password_reset_testing.enabled')) {
            $whitelist = array_map(
                static fn ($allowedEmail) => strtolower(trim((string) $allowedEmail)),
                (array) config('auth.password_reset_testing.whitelist', [])
            );

            if (! in_array($email, $whitelist, true)) {
                return response()->json(['message' => self::GENERIC_MESSAGE]);
            }
        }

        Password::sendResetLink(['email' => $user->email]);

        return response()->json(['message' => self::GENERIC_MESSAGE]);
    }

    public function showResetForm(Request $request)
    {
        return view('auth.reset-password', [
            'token' => $request->query('token', ''),
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user || $user->role !== 'driver') {
            if ($user?->role === 'admin') {
                Log::warning('Blocked password reset completion for admin account.', [
                    'user_id' => $user->getKey(),
                    'ip' => $request->ip(),
                ]);
            }

            return $this->invalidResetResponse($request, $email, $validated['token']);
        }

        $resetPerformed = false;
        $status = Password::reset(
            [
                'email' => $user->email,
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
                'token' => $validated['token'],
            ],
            function (User $resetUser, string $password) use (&$resetPerformed, $request): void {
                if ($resetUser->role !== 'driver') {
                    if ($resetUser->role === 'admin') {
                        Log::warning('Blocked password reset completion for admin account.', [
                            'user_id' => $resetUser->getKey(),
                            'ip' => $request->ip(),
                        ]);
                    }

                    return;
                }

                $resetUser->forceFill(['password' => $password])->save();
                $resetUser->tokens()->delete();
                event(new PasswordReset($resetUser));
                $resetPerformed = true;
            }
        );

        if ($status !== Password::PASSWORD_RESET || ! $resetPerformed) {
            return $this->invalidResetResponse($request, $email, $validated['token']);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Password has been reset successfully.']);
        }

        return redirect()
            ->route('password.reset')
            ->with('status', 'Password has been reset successfully.');
    }

    private function invalidResetResponse(Request $request, string $email, string $token)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => self::INVALID_TOKEN_MESSAGE], 422);
        }

        return back()
            ->withInput(['email' => $email, 'token' => $token])
            ->withErrors(['email' => self::INVALID_TOKEN_MESSAGE]);
    }
}
