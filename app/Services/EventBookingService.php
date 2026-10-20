<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class EventBookingService
{
    public function __construct(
        protected EventWaitlistService $waitlistService,
        protected EventRefundService $refundService,
        protected EventReminderService $reminderService
    ) {}

    /**
     * Create an online seat hold for an attendee inside a database transaction with a lock.
     * Prevents overbooking under high concurrency.
     *
     * @throws Exception
     */
    public function createOnlineHold(Event $event, User $user, string $bookingType = 'training_only'): array
    {
        return DB::transaction(function () use ($event, $user, $bookingType) {
            // Lock event row
            $lockedEvent = Event::where('id', $event->id)->lockForUpdate()->firstOrFail();

            if ($lockedEvent->occupied_seats_count >= $lockedEvent->capacity) {
                throw new Exception('This event is fully booked. You may join the waitlist.');
            }

            $trainingFee = (int) $lockedEvent->training_fee_kes;
            $accommodationFee = ($bookingType === 'training_and_accommodation' && $lockedEvent->has_accommodation)
                ? (int) $lockedEvent->accommodation_fee_kes
                : 0;
            $totalFee = $trainingFee + $accommodationFee;

            $holdMinutes = (int) Setting::getValue('bootcamp_seat_hold_online_minutes', 15);
            $holdExpiresAt = now()->addMinutes($holdMinutes);

            // Create Event Booking with status 'held'
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

            $title = "{$lockedEvent->title} (".($bookingType === 'training_and_accommodation' ? 'Training + Accommodation' : 'Training Only').')';

            $order->items()->create([
                'item_type' => 'bootcamp_booking',
                'item_id' => $booking->id,
                'title' => $title,
                'price_kes' => $totalFee,
                'vat_kes' => 0,
                'meta' => [
                    'event_id' => $lockedEvent->id,
                    'booking_id' => $booking->id,
                    'booking_type' => $bookingType,
                    'training_fee_kes' => $trainingFee,
                    'accommodation_fee_kes' => $accommodationFee,
                ],
            ]);

            $booking->update(['order_id' => $order->id]);

            activity()
                ->performedOn($booking)
                ->causedBy($user)
                ->withProperties(['hold_expires_at' => $holdExpiresAt->toISOString()])
                ->log('Online seat hold placed');

            return [
                'booking' => $booking->fresh(['event', 'order']),
                'order' => $order->fresh(['items']),
            ];
        });
    }

    /**
     * Create a cash seat hold by finance staff for in-person payments.
     * Uses bootcamp_seat_hold_cash_hours (default 48h) from settings.
     *
     * @throws Exception
     */
    public function createCashHold(Event $event, User $attendee, User $staff, string $bookingType = 'training_only'): array
    {
        return DB::transaction(function () use ($event, $attendee, $staff, $bookingType) {
            $lockedEvent = Event::where('id', $event->id)->lockForUpdate()->firstOrFail();

            if ($lockedEvent->occupied_seats_count >= $lockedEvent->capacity) {
                throw new Exception('Cannot place cash hold: event is fully booked.');
            }

            $trainingFee = (int) $lockedEvent->training_fee_kes;
            $accommodationFee = ($bookingType === 'training_and_accommodation' && $lockedEvent->has_accommodation)
                ? (int) $lockedEvent->accommodation_fee_kes
                : 0;
            $totalFee = $trainingFee + $accommodationFee;

            $cashHoldHours = (int) Setting::getValue('bootcamp_seat_hold_cash_hours', 48);
            $holdExpiresAt = now()->addHours($cashHoldHours);

            $booking = EventBooking::create([
                'event_id' => $lockedEvent->id,
                'user_id' => $attendee->id,
                'booking_type' => $bookingType,
                'training_fee_kes' => $trainingFee,
                'accommodation_fee_kes' => $accommodationFee,
                'total_fee_kes' => $totalFee,
                'status' => 'held',
                'hold_type' => 'cash',
                'held_by' => $staff->id,
                'hold_expires_at' => $holdExpiresAt,
            ]);

            $exchangeRate = (float) Setting::getValue('usd_exchange_rate', 130.00);
            $usdCents = (int) ceil($totalFee / $exchangeRate);

            $order = Order::create([
                'user_id' => $attendee->id,
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
                'title' => "{$lockedEvent->title} (".($bookingType === 'training_and_accommodation' ? 'Training + Accommodation' : 'Training Only').')',
                'price_kes' => $totalFee,
                'vat_kes' => 0,
                'meta' => [
                    'event_id' => $lockedEvent->id,
                    'booking_id' => $booking->id,
                    'booking_type' => $bookingType,
                    'training_fee_kes' => $trainingFee,
                    'accommodation_fee_kes' => $accommodationFee,
                    'is_cash_hold' => true,
                    'held_by_user_id' => $staff->id,
                ],
            ]);

            $booking->update(['order_id' => $order->id]);

            activity()
                ->performedOn($booking)
                ->causedBy($staff)
                ->withProperties(['hold_expires_at' => $holdExpiresAt->toISOString()])
                ->log('Finance cash seat hold created');

            return [
                'booking' => $booking->fresh(['event', 'order']),
                'order' => $order->fresh(['items']),
            ];
        });
    }

    /**
     * Process payment confirmation for a booking:
     * - If hold expired AND seat is gone -> mark unseated, notify finance_manager, trigger automatic refund.
     * - If seat is still free or hold was valid -> confirm booking.
     */
    public function processPaymentConfirmation(EventBooking $booking, Order $order, Payment $payment): void
    {
        DB::transaction(function () use ($booking, $order, $payment) {
            $event = Event::where('id', $booking->event_id)->lockForUpdate()->firstOrFail();

            $isHoldExpired = $booking->status === 'held'
                && $booking->hold_expires_at
                && now()->greaterThan($booking->hold_expires_at);

            // Count other occupied seats
            $otherOccupied = EventBooking::where('event_id', $event->id)
                ->where('id', '!=', $booking->id)
                ->where(function ($query) {
                    $query->whereIn('status', ['confirmed', 'attended'])
                        ->orWhere(function ($sub) {
                            $sub->where('status', 'held')
                                ->where('hold_expires_at', '>', now());
                        });
                })
                ->count();

            if ($isHoldExpired && $otherOccupied >= $event->capacity) {
                // UNSEATED FLOW
                $booking->update([
                    'status' => 'unseated',
                    'unseated_at' => now(),
                    'unseated_reason' => 'Payment confirmed after hold expired and all event seats were occupied.',
                ]);

                activity()
                    ->performedOn($booking)
                    ->withProperties(['order_id' => $order->id, 'payment_id' => $payment->id])
                    ->log('Booking marked unseated due to expired hold and full capacity');

                // Notify Finance Manager
                NotificationLog::create([
                    'channel' => 'mail',
                    'notification_type' => 'event_unseated_alert',
                    'recipient' => 'finance@bmas.com',
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                // Start automatic refund
                $this->refundService->createAutomaticRefundForUnseatedBooking($booking, $order, $payment);
            } else {
                // CONFIRM BOOKING
                $booking->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                ]);

                activity()
                    ->performedOn($booking)
                    ->causedBy($order->user)
                    ->log('Event booking confirmed');

                // Dispatch confirmation email & SMS
                $this->reminderService->sendBookingConfirmation($booking);
            }
        });
    }

    /**
     * Check in an attendee on the event day.
     *
     * @throws Exception
     */
    public function checkInAttendee(EventBooking $booking, User $coordinator): EventBooking
    {
        if ($booking->status !== 'confirmed') {
            throw new Exception("Cannot check in booking with status '{$booking->status}'. Booking must be confirmed.");
        }

        $booking->update([
            'status' => 'attended',
            'checked_in_at' => now(),
            'checked_in_by' => $coordinator->id,
        ]);

        activity()
            ->performedOn($booking)
            ->causedBy($coordinator)
            ->log('Attendee checked in for event');

        return $booking;
    }

    /**
     * Release expired holds and notify waitlist.
     */
    public function releaseExpiredHolds(): int
    {
        $expiredBookings = EventBooking::where('status', 'held')
            ->where('hold_expires_at', '<', now())
            ->get();

        $count = 0;
        $eventIds = [];

        foreach ($expiredBookings as $booking) {
            $booking->update(['status' => 'cancelled']);
            $eventIds[] = $booking->event_id;
            $count++;
        }

        foreach (array_unique($eventIds) as $eventId) {
            $event = Event::find($eventId);
            if ($event) {
                $this->waitlistService->offerNextInWaitlist($event);
            }
        }

        return $count;
    }
}
