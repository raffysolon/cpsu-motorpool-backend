<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
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

        $response->assertOk()->assertJson(['message' => self::GENERIC_MESSAGE]);
        $response->assertJsonPath('attempts_remaining', 2)->assertJsonPath('retry_after', 0);
        Notification::assertSentTo($driver, ResetPasswordNotification::class);
    }

    public function test_admin_can_request_a_reset_link(): void
    {
        Notification::fake();
        $admin = $this->createUser('admin@example.com', 'admin');
        config(['auth.password_reset_testing.enabled' => false]);

        $response = $this->postJson('/api/password/forgot', [
            'email' => $admin->email,
        ]);

        $response->assertOk()->assertJson(['message' => self::GENERIC_MESSAGE]);
        Notification::assertSentTo($admin, ResetPasswordNotification::class);
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

        $response->assertOk()->assertJson(['message' => self::GENERIC_MESSAGE]);
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

        $response->assertOk()->assertJson(['message' => self::GENERIC_MESSAGE]);
        Notification::assertNothingSent();
    }

    public function test_reset_request_is_limited_to_three_attempts_per_email(): void
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

    public function test_cooldown_starts_at_third_attempt_and_does_not_extend(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/password/forgot', ['email' => 'unknown@example.com'])->assertOk();
        }
        $this->travel(60)->seconds();
        $this->postJson('/api/password/forgot', ['email' => 'UNKNOWN@example.com'])->assertOk();
        $this->postJson('/api/password/forgot', ['email' => 'unknown@example.com'])
            ->assertStatus(429)->assertJsonPath('retry_after', 300)->assertHeader('Retry-After', '300');
        $this->travel(299)->seconds();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.5'])
            ->postJson('/api/password/forgot', ['email' => 'unknown@example.com'])
            ->assertStatus(429)->assertJsonPath('retry_after', 1);
        $this->travel(1)->seconds();
        $this->postJson('/api/password/forgot', ['email' => 'unknown@example.com'])->assertOk();
        $this->travelBack();
    }

    public function test_another_email_has_its_own_quota(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/password/forgot', ['email' => 'unknown@example.com'])->assertOk();
        }
        $this->postJson('/api/password/forgot', ['email' => 'other@example.com'])->assertOk();
        $this->postJson('/api/password/forgot', ['email' => 'unknown@example.com'])->assertStatus(429);
    }

    public function test_blocked_request_never_sends_another_email(): void
    {
        Notification::fake();
        config(['auth.password_reset_testing.enabled' => false, 'auth.passwords.users.throttle' => 0]);
        $driver = $this->createUser('limited@example.com', 'driver');
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/password/forgot', ['email' => $driver->email])->assertOk();
        }
        $this->postJson('/api/password/forgot', ['email' => $driver->email])->assertStatus(429);
        Notification::assertSentTimes(ResetPasswordNotification::class, 3);
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
            ->assertSee('name="_token"', false)
            ->assertSee('Show new password')->assertSee('Show password confirmation');
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

    public function test_admin_can_complete_a_password_reset(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $token = Password::broker()->createToken($admin);
        $admin->createToken('existing-session');
        $response = $this->postJson('/reset-password', [
            'email' => $admin->email,
            'token' => $token,
            'password' => 'A-new-strong-password-123!',
            'password_confirmation' => 'A-new-strong-password-123!',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('A-new-strong-password-123!', $admin->fresh()->password));
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_settings_requires_current_password_and_only_emails_the_signed_in_account(): void
    {
        Notification::fake();
        config(['auth.password_reset_testing.enabled' => false]);
        foreach (['admin', 'driver'] as $role) {
            $user = $this->createUser($role.'@example.com', $role);
            Sanctum::actingAs($user);
            $this->putJson('/api/change-password', ['current_password' => 'wrong'])->assertUnprocessable();
            Notification::assertNothingSentTo($user);
            $this->putJson('/api/change-password', [
                'current_password' => 'Existing-password-123!',
                'email' => 'someone-else@example.com',
                'new_password' => 'Do-not-apply-this-123!',
            ])->assertOk();
            Notification::assertSentTo($user, ResetPasswordNotification::class);
            $this->assertTrue(Hash::check('Existing-password-123!', $user->fresh()->password));
            $this->putJson('/api/change-password', ['current_password' => 'Existing-password-123!'])
                ->assertStatus(429)->assertHeader('Retry-After');
        }
    }

    public function test_settings_requires_authentication(): void
    {
        $this->putJson('/api/change-password', ['current_password' => 'Anything123!'])->assertUnauthorized();
    }

    public function test_invalid_expired_and_mismatched_tokens_cannot_change_admin_password(): void
    {
        $user = $this->createUser('admin@example.com', 'admin');
        $token = Password::broker()->createToken($user);
        foreach ([['token' => 'invalid'], ['email' => 'other@example.com'], ['password_confirmation' => 'Mismatch123!'], ['password' => 'weak', 'password_confirmation' => 'weak']] as $override) {
            $this->postJson('/reset-password', array_merge([
                'email' => $user->email, 'token' => $token,
                'password' => 'Strong-password123!', 'password_confirmation' => 'Strong-password123!',
            ], $override))->assertUnprocessable();
        }
        $this->travel(61)->minutes();
        $this->postJson('/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'Strong-password123!', 'password_confirmation' => 'Strong-password123!',
        ])->assertUnprocessable();
        $this->assertTrue(Hash::check('Existing-password-123!', $user->fresh()->password));
        $this->travelBack();
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
