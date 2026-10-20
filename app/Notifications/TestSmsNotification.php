<?php

namespace App\Notifications;

use App\Channels\AfricasTalkingSmsChannel;
use App\Channels\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TestSmsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $customMessage = 'Bariton Music Academy test SMS notification.'
    ) {}

    public function via(object $notifiable): array
    {
        return [AfricasTalkingSmsChannel::class];
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return new SmsMessage($this->customMessage);
    }
}
