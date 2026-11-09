<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_MESSAGE = 'If an account exists with this email, you will receive password reset instructions.';

    public function test_driver_can_request_a_reset_link(): void
    {
        Notification::fake();
        config([
            'auth.password_reset_testing.enabled' => false,
        ]);
        $driver = $this->createUser('driver@example.com', 'driver');

        $response = $this->postJson('/api/password/forgot', [
            'email' => 'DRIVER@example.com',
        ]);

        $response->assertOk()->assertExactJson(['message' => self::GENERIC_MESSAGE]);
        Notification::assertSentTo($driver, ResetPasswordNotification::class);
    }

    public function test_admin_reset_request_is_blocked_and_logged(): void
    {
        Notification::fake();
        $admin = $this->createUser('admin@example.com', 'admin');
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Blocked password reset request for admin account.',
                \Mockery::on(fn (array $context) => $context['user_id'] === $admin->id)
            );

        $response = $this->postJson('/api/password/forgot', [
            'email' => $admin->email,
        ]);

        $response->assertOk()->assertExactJson(['message' => self::GENERIC_MESSAGE]);
        Notification::assertNothingSent();
    }

    public function test_empty_test_whitelist_blocks_reset_delivery(): void
    {
        Notification::fake();
        config([
            'auth.password_reset_testing.enabled' => true,
            'auth.password_reset_testing.whitelist' => [],
        ]);
        $driver = $this->createUser('driver@example.com', 'driver');

        $response = $this->postJson('/api/password/forgot', [
            'email' => $driver->email,
        ]);

        $response->assertOk()->assertExactJson(['message' => self::GENERIC_MESSAGE]);
        Notification::assertNothingSent();
    }

    public function test_driver_not_in_test_whitelist_does_not_receive_a_link(): void
    {
        Notification::fake();
        config([
            'auth.password_reset_testing.enabled' => true,
            'auth.password_reset_testing.whitelist' => ['allowed@example.com'],
        ]);
        $driver = $this->createUser('driver@example.com', 'driver');

        $response = $this->postJson('/api/password/forgot', [
            'email' => $driver->email,
        ]);

        $response->assertOk()->assertExactJson(['message' => self::GENERIC_MESSAGE]);
        Notification::assertNothingSent();
    }

    public function test_reset_request_is_limited_to_three_attempts_per_hour_per_ip(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/password/forgot', [
                'email' => 'unknown@example.com',
            ])->assertOk();
        }

        $this->postJson('/api/password/forgot', [
            'email' => 'unknown@example.com',
        ])->assertTooManyRequests();
    }

    public function test_reset_form_uses_email_and_token_from_the_link(): void
    {
        $response = $this->get(route('password.reset', [
            'token' => 'reset-token',
            'email' => 'driver@example.com',
        ]));

        $response
            ->assertOk()
            ->assertSee('driver@example.com')
            ->assertSee('reset-token', false)
            ->assertSee('name="_token"', false);
    }

    public function test_driver_can_use_a_reset_token_once_and_all_sanctum_tokens_are_revoked(): void
    {
        $driver = $this->createUser('driver@example.com', 'driver');
        $driver->createToken('test-token');
        $token = Password::broker()->createToken($driver);

        $response = $this->postJson('/reset-password', [
            'email' => $driver->email,
            'token' => $token,
            'password' => 'A-new-strong-password-123!',
            'password_confirmation' => 'A-new-strong-password-123!',
        ]);

        $response
            ->assertOk()
            ->assertExactJson(['message' => 'Password has been reset successfully.']);
        $this->assertTrue(Hash::check('A-new-strong-password-123!', $driver->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $driver->id,
        ]);

        $secondUse = $this->postJson('/reset-password', [
            'email' => $driver->email,
            'token' => $token,
            'password' => 'Another-strong-password-123!',
            'password_confirmation' => 'Another-strong-password-123!',
        ]);
        $secondUse->assertUnprocessable();
    }

    public function test_admin_cannot_complete_a_password_reset(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $token = Password::broker()->createToken($admin);
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Blocked password reset completion for admin account.',
                \Mockery::on(fn (array $context) => $context['user_id'] === $admin->id)
            );

        $response = $this->postJson('/reset-password', [
            'email' => $admin->email,
            'token' => $token,
            'password' => 'A-new-strong-password-123!',
            'password_confirmation' => 'A-new-strong-password-123!',
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'Invalid or expired reset token.',
        ]);
        $this->assertTrue(Hash::check('Existing-password-123!', $admin->fresh()->password));
    }

    private function createUser(string $email, string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => 'Existing-password-123!',
            'role' => $role,
        ]);
    }
}
