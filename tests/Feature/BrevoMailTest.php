<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class BrevoMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'mail.default' => 'brevo',
            'mail.mailers.brevo.key' => 'test-key',
            'mail.from.address' => 'sender@example.com',
            'mail.from.name' => 'CPSU MotorPool',
        ]);
        Mail::purge('brevo');
    }

    public function test_password_reset_notification_uses_brevo_https(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<test@example.com>'], 201)]);
        $user = new User;
        $user->name = 'Driver';
        $user->email = 'driver@example.com';
        $user->notify(new ResetPasswordNotification('test-reset-token'));

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && $request->hasHeader('api-key', 'test-key')
            && $request['sender']['email'] === 'sender@example.com'
            && $request['to'][0]['email'] === 'driver@example.com'
            && str_contains($request['htmlContent'], 'test-reset-token')
        );
        Http::assertSentCount(1);
    }

    public function test_api_rejection_is_reported_without_sensitive_response(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'private data'], 401)]);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Brevo rejected email (HTTP 401)');
        Mail::raw('Test', fn ($mail) => $mail->to('driver@example.com')->subject('Test'));
    }

    public function test_missing_key_fails_before_network_request(): void
    {
        config(['mail.mailers.brevo.key' => '']);
        Mail::purge('brevo');
        Http::fake();
        try {
            Mail::raw('Test', fn ($mail) => $mail->to('driver@example.com')->subject('Test'));
            $this->fail('Expected missing-key failure.');
        } catch (TransportException $error) {
            $this->assertSame('BREVO_API_KEY is missing.', $error->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_connection_failure_is_sanitized(): void
    {
        Http::fake(['api.brevo.com/*' => Http::failedConnection()]);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Brevo HTTPS connection failed or timed out.');
        Mail::raw('Test', fn ($mail) => $mail->to('driver@example.com')->subject('Test'));
    }
}
