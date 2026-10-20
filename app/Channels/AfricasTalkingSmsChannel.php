<?php

namespace App\Channels;

use App\Channels\Messages\SmsMessage;
use App\Services\AfricasTalkingSmsService;
use Illuminate\Notifications\Notification;

class AfricasTalkingSmsChannel
{
    public function __construct(
        protected AfricasTalkingSmsService $smsService
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);

        if (is_string($message)) {
            $message = new SmsMessage($message);
        }

        $to = $message->to ?: $notifiable->routeNotificationFor('sms', $notification) ?: $notifiable->phone;

        if (! $to) {
            return;
        }

        $notifiableType = is_object($notifiable) ? get_class($notifiable) : null;
        $notifiableId = is_object($notifiable) && isset($notifiable->id) ? (int) $notifiable->id : null;

        $this->smsService->send(
            to: $to,
            message: $message->content,
            notifiableType: $notifiableType,
            notifiableId: $notifiableId,
            type: class_basename($notification)
        );
    }
}
