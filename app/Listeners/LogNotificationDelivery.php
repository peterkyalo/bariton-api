<?php

namespace App\Listeners;

use App\Models\NotificationLog;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

class LogNotificationDelivery
{
    public function handleSent(NotificationSent $event): void
    {
        // SMS is already logged by AfricasTalkingSmsChannel
        if ($event->channel === 'sms' || $event->channel === 'App\Channels\AfricasTalkingSmsChannel') {
            return;
        }

        $recipient = $this->resolveRecipient($event->notifiable, $event->channel);
        $notifiableType = is_object($event->notifiable) ? get_class($event->notifiable) : null;
        $notifiableId = is_object($event->notifiable) && isset($event->notifiable->id) ? (int) $event->notifiable->id : null;

        NotificationLog::create([
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'channel' => $event->channel,
            'notification_type' => class_basename($event->notification),
            'recipient' => $recipient,
            'status' => 'delivered',
            'sent_at' => now(),
        ]);
    }

    public function handleFailed(NotificationFailed $event): void
    {
        if ($event->channel === 'sms' || $event->channel === 'App\Channels\AfricasTalkingSmsChannel') {
            return;
        }

        $recipient = $this->resolveRecipient($event->notifiable, $event->channel);
        $notifiableType = is_object($event->notifiable) ? get_class($event->notifiable) : null;
        $notifiableId = is_object($event->notifiable) && isset($event->notifiable->id) ? (int) $event->notifiable->id : null;

        NotificationLog::create([
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'channel' => $event->channel,
            'notification_type' => class_basename($event->notification),
            'recipient' => $recipient,
            'status' => 'failed',
            'error' => is_string($event->data) ? $event->data : json_encode($event->data),
            'sent_at' => now(),
        ]);
    }

    protected function resolveRecipient(mixed $notifiable, string $channel): string
    {
        if (is_string($notifiable)) {
            return $notifiable;
        }

        if (is_object($notifiable)) {
            if ($channel === 'mail' && isset($notifiable->email)) {
                return (string) $notifiable->email;
            }
            if (isset($notifiable->phone)) {
                return (string) $notifiable->phone;
            }
            if (isset($notifiable->email)) {
                return (string) $notifiable->email;
            }
        }

        return 'unknown';
    }
}
