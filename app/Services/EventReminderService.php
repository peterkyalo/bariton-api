<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\NotificationLog;
use App\Models\User;

class EventReminderService
{
    /**
     * Send instant booking confirmation via Email and SMS.
     */
    public function sendBookingConfirmation(EventBooking $booking): void
    {
        $user = $booking->user;
        $event = $booking->event;

        // 1. Email notification
        NotificationLog::create([
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channel' => 'mail',
            'notification_type' => 'event_booking_confirmation',
            'recipient' => $user->email,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // 2. SMS notification (if user phone exists)
        if (! empty($user->phone)) {
            NotificationLog::create([
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'channel' => 'sms',
                'notification_type' => 'event_booking_confirmation_sms',
                'recipient' => $user->phone,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }
    }

    /**
     * Send batch event reminders by email and SMS to all confirmed attendees.
     */
    public function sendRemindersForEvent(Event $event): array
    {
        $confirmedBookings = $event->bookings()
            ->whereIn('status', ['confirmed'])
            ->with('user')
            ->get();

        $emailCount = 0;
        $smsCount = 0;

        foreach ($confirmedBookings as $booking) {
            $user = $booking->user;

            // Email reminder
            NotificationLog::create([
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'channel' => 'mail',
                'notification_type' => 'event_upcoming_reminder',
                'recipient' => $user->email,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            $emailCount++;

            // SMS reminder
            if (! empty($user->phone)) {
                NotificationLog::create([
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'channel' => 'sms',
                    'notification_type' => 'event_upcoming_reminder_sms',
                    'recipient' => $user->phone,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                $smsCount++;
            }
        }

        return [
            'attendees_count' => $confirmedBookings->count(),
            'emails_sent' => $emailCount,
            'sms_sent' => $smsCount,
        ];
    }
}
