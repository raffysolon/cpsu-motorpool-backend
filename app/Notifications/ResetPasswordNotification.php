<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable)
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        // Force HTTPS if not already
        if (str_starts_with($url, 'http://')) {
            $url = str_replace('http://', 'https://', $url);
        }

        return (new MailMessage)
            ->subject('Reset your CPSU Motor Pool password')
            ->view('emails.password-reset', [
                'url' => $url,
                'name' => $notifiable->name,
            ]);
    }
}
