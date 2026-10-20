<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventWaitlist;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class EventWaitlistService
{
    /**
     * Join waitlist for a full event.
     *
     * @throws Exception
     */
    public function joinWaitlist(Event $event, User $user, string $bookingType = 'training_only'): EventWaitlist
    {
        $existing = EventWaitlist::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['waiting', 'offered'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $waitlist = EventWaitlist::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'booking_type' => $bookingType,
            'status' => 'waiting',
        ]);

        activity()
            ->performedOn($waitlist)
            ->causedBy($user)
            ->log('Student joined event waitlist');

        return $waitlist;
    }

    /**
     * Offer next freed seat in waitlist with a fresh hold inside a database lock.
     */
    public function offerNextInWaitlist(Event $event): ?EventBooking
    {
        return DB::transaction(function () use ($event) {
            $lockedEvent = Event::where('id', $event->id)->lockForUpdate()->firstOrFail();

            if ($lockedEvent->available_seats_count <= 0) {
                return null;
            }

            $nextWaitlist = EventWaitlist::where('event_id', $lockedEvent->id)
                ->where('status', 'waiting')
                ->orderBy('created_at')
                ->first();

            if (! $nextWaitlist) {
                return null;
            }

            $user = $nextWaitlist->user;
            $bookingType = $nextWaitlist->booking_type ?: 'training_only';

            $trainingFee = (int) $lockedEvent->training_fee_kes;
            $accommodationFee = ($bookingType === 'training_and_accommodation' && $lockedEvent->has_accommodation)
                ? (int) $lockedEvent->accommodation_fee_kes
                : 0;
            $totalFee = $trainingFee + $accommodationFee;

            $holdMinutes = (int) Setting::getValue('bootcamp_seat_hold_online_minutes', 15);
            $holdExpiresAt = now()->addMinutes($holdMinutes);

            // Create fresh hold booking
            $booking = EventBooking::create([
                'event_id' => $lockedEvent->id,
                'user_id' => $user->id,
                'booking_type' => $bookingType,
                'training_fee_kes' => $trainingFee,
                'accommodation_fee_kes' => $accommodationFee,
                'total_fee_kes' => $totalFee,
                'status' => 'held',
                'hold_type' => 'online',
                'hold_expires_at' => $holdExpiresAt,
            ]);

            // Create corresponding Order
            $exchangeRate = (float) Setting::getValue('usd_exchange_rate', 130.00);
            $usdCents = (int) ceil($totalFee / $exchangeRate);

            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total_kes' => $totalFee,
                'vat_kes' => 0,
                'vat_rate_percent' => 0.00,
                'exchange_rate_used' => $exchangeRate,
                'total_usd_cents' => $usdCents,
                'currency' => 'KES',
                'expires_at' => $holdExpiresAt,
            ]);

            $order->items()->create([
                'item_type' => 'bootcamp_booking',
                'item_id' => $booking->id,
                'title' => "{$lockedEvent->title} (Waitlist Offer: " . ($bookingType === 'training_and_accommodation' ? 'Training + Accommodation' : 'Training Only') . ')',
                'price_kes' => $totalFee,
                'vat_kes' => 0,
                'meta' => [
                    'event_id' => $lockedEvent->id,
                    'booking_id' => $booking->id,
                    'waitlist_id' => $nextWaitlist->id,
                    'booking_type' => $bookingType,
                    'training_fee_kes' => $trainingFee,
                    'accommodation_fee_kes' => $accommodationFee,
                ],
            ]);

            $booking->update(['order_id' => $order->id]);

            // Update waitlist entry
            $nextWaitlist->update([
                'status' => 'offered',
                'offered_at' => now(),
                'offer_expires_at' => $holdExpiresAt,
                'held_booking_id' => $booking->id,
            ]);

            activity()
                ->performedOn($nextWaitlist)
                ->withProperties(['booking_id' => $booking->id, 'hold_expires_at' => $holdExpiresAt->toISOString()])
                ->log('Seat offered to waitlist candidate with fresh hold');

            // Send notification
            NotificationLog::create([
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'channel' => 'mail',
                'notification_type' => 'event_waitlist_seat_offered',
                'recipient' => $user->email,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return $booking;
        });
    }
}
