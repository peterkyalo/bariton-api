<?php

namespace App\Notifications;

use App\Channels\AfricasTalkingSmsChannel;
use App\Channels\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuardianConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        $channels = ['mail'];
        if (! empty($notifiable->phone)) {
            $channels[] = AfricasTalkingSmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = config('app.frontend_url', 'http://localhost:5173').'/login';

        return (new MailMessage)
            ->subject('Guardian Consent Approved - Welcome to Bariton Music Academy!')
            ->greeting("Hello {$notifiable->name},")
            ->line('Great news! Your parent or legal guardian has confirmed their consent for your student account.')
            ->line('Your account is now fully active. You can log in and begin your musical learning journey.')
            ->action('Log In to Bariton Academy', $loginUrl);
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return (new SmsMessage)
            ->line("Bariton Academy: Guardian consent confirmed for {$notifiable->name}. Your student account is now active! Log in at app.bmas.com");
    }
}
