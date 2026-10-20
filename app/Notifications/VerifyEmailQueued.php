<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailQueued extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        $temporarySignedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        $parsed = parse_url($temporarySignedUrl);
        parse_str($parsed['query'] ?? '', $queryParams);

        $verifyUrl = "{$frontendUrl}/verify-email?id={$notifiable->getKey()}&hash=".sha1($notifiable->getEmailForVerification()).'&'.http_build_query($queryParams);

        return (new MailMessage)
            ->subject('Verify Your Email Address - Bariton Music Academy')
            ->greeting("Hello {$notifiable->name},")
            ->line('Welcome to Bariton Music Academy! Please click the button below to verify your email address and activate your account.')
            ->action('Verify Email Address', $verifyUrl)
            ->line('If you did not create an account, no further action is required.');
    }
}
