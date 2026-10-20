<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\PaymentManager;
use App\Services\Receipts\CreditNoteIssuerInterface;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EventRefundService
{
    public function __construct(
        protected PaymentManager $paymentManager,
        protected CreditNoteIssuerInterface $creditNoteIssuer,
        protected EventWaitlistService $waitlistService
    ) {}

    /**
     * Validate refund eligibility for an event booking.
     */
    public function validateEligibility(EventBooking $booking): array
    {
        if ($booking->status !== 'confirmed') {
            return [
                'eligible' => false,
                'message' => "Only confirmed bookings can be refunded (current status: {$booking->status}).",
            ];
        }

        $event = $booking->event;
        $windowDays = (int) Setting::getValue('bootcamp_refund_window_days', 7);

        // Refund request must be submitted at least $windowDays before event starts
        if ($event->start_date && now()->diffInDays($event->start_date, false) < $windowDays) {
            return [
                'eligible' => false,
                'message' => "Refunds for physical events must be requested at least {$windowDays} days prior to the event start date.",
            ];
        }

        $existing = RefundRequest::where('event_booking_id', $booking->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existing) {
            return [
                'eligible' => false,
                'message' => 'A refund request for this booking is already pending or approved.',
            ];
        }

        return ['eligible' => true];
    }

    /**
     * Submit a student or coordinator refund request for an event booking.
     *
     * @throws Exception
     */
    public function requestRefund(
        EventBooking $booking,
        User $requester,
        string $reason,
        UploadedFile|string|null $proofDocument = null
    ): RefundRequest {
        $check = $this->validateEligibility($booking);
        if (! $check['eligible']) {
            throw new InvalidArgumentException($check['message']);
        }

        $proofPath = null;
        if ($proofDocument instanceof UploadedFile) {
            $proofPath = $proofDocument->store('proofs/refunds', 'public');
        } elseif (is_string($proofDocument)) {
            $proofPath = $proofDocument;
        }

        $order = $booking->order;
        $orderItem = $order?->items()->where('item_type', 'bootcamp_booking')->where('item_id', $booking->id)->first();

        $amountKes = $booking->total_fee_kes;
        $exchangeRate = $order?->exchange_rate_used ?: 130.00;
        $amountUsdCents = (int) ceil($amountKes / $exchangeRate);

        $refundRequest = RefundRequest::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order?->id,
            'order_item_id' => $orderItem?->id,
            'event_booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'amount_kes' => $amountKes,
            'amount_usd_cents' => $amountUsdCents,
            'currency' => $order?->currency ?: 'KES',
            'reason' => $reason,
            'proof_document_path' => $proofPath,
            'status' => 'pending',
        ]);

        activity()
            ->performedOn($booking)
            ->causedBy($requester)
            ->withProperties(['refund_request_id' => $refundRequest->id, 'amount_kes' => $amountKes])
            ->log('Refund request submitted for event booking');

        return $refundRequest;
    }

    /**
     * Create and execute an automatic full refund when a payment arrives after hold expired
     * and seats are completely occupied (unseated status).
     */
    public function createAutomaticRefundForUnseatedBooking(
        EventBooking $booking,
        Order $order,
        Payment $payment
    ): CreditNote {
        return DB::transaction(function () use ($booking, $order, $payment) {
            $amountKes = $booking->total_fee_kes;
            $exchangeRate = $order->exchange_rate_used ?: 130.00;
            $amountUsdCents = (int) ceil($amountKes / $exchangeRate);

            $refundRef = null;
            if (in_array($payment->gateway, ['cash', 'mpesa'])) {
                $refundRef = 'AUTO-UNSEATED-' . strtoupper(Str::random(10));
            } else {
                $gateway = $this->paymentManager->gateway($payment->gateway);
                $result = $gateway->refund($payment, $amountKes, 'Automatic refund for unseated booking due to expired hold.');
                if (! $result->success) {
                    $refundRef = 'PENDING-MANUAL-REFUND';
                } else {
                    $refundRef = $result->reference;
                }
            }

            $orderItem = $order->items()->where('item_type', 'bootcamp_booking')->where('item_id', $booking->id)->first();

            $refundRequest = RefundRequest::create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $order->id,
                'order_item_id' => $orderItem?->id,
                'event_booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'amount_kes' => $amountKes,
                'amount_usd_cents' => $amountUsdCents,
                'currency' => $order->currency ?: 'KES',
                'reason' => 'Automated refund: Booking unseated due to expired hold and full capacity.',
                'status' => 'approved',
                'reviewed_by' => null,
                'reviewed_at' => now(),
                'refund_reference' => $refundRef,
            ]);

            $booking->update(['status' => 'refunded']);
            $order->update(['refund_status' => 'full']);

            activity()
                ->performedOn($booking)
                ->withProperties(['refund_reference' => $refundRef])
                ->log('Automatic refund processed for unseated booking');

            return $this->creditNoteIssuer->issue($refundRequest);
        });
    }

    /**
     * Finance Manager approves a refund request:
     * - Executes gateway refund / logs manual reference
     * - Marks booking as refunded
     * - Issues official Credit Note
     * - Rotates next waitlist candidate into the freed seat!
     */
    public function approve(RefundRequest $refundRequest, User $reviewer, ?string $manualReference = null): CreditNote
    {
        return DB::transaction(function () use ($refundRequest, $reviewer, $manualReference) {
            $order = $refundRequest->order;
            $payment = $order?->successfulPayment ?: $order?->payments()->latest()->first();
            $booking = $refundRequest->eventBooking ?: ($refundRequest->event_booking_id ? EventBooking::find($refundRequest->event_booking_id) : null);

            $refundRef = null;

            if ($payment) {
                if (in_array($payment->gateway, ['cash', 'mpesa'])) {
                    $refundRef = $manualReference ?: 'MAN-REF-' . strtoupper(Str::random(10));
                } else {
                    $gateway = $this->paymentManager->gateway($payment->gateway);
                    $result = $gateway->refund($payment, $refundRequest->amount_kes, $refundRequest->reason);
                    if (! $result->success) {
                        throw new \RuntimeException($result->message);
                    }
                    $refundRef = $result->reference;
                }
            } else {
                $refundRef = $manualReference ?: 'REF-' . strtoupper(Str::random(10));
            }

            if ($booking) {
                $booking->update(['status' => 'refunded']);
            }

            $refundRequest->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'refund_reference' => $refundRef,
            ]);

            if ($order) {
                $totalRefunded = $order->refundRequests()->where('status', 'approved')->sum('amount_kes');
                $refundStatus = ($totalRefunded >= $order->total_kes) ? 'full' : 'partial';
                $order->update(['refund_status' => $refundStatus]);
            }

            // Issue statutory Credit Note
            $creditNote = $this->creditNoteIssuer->issue($refundRequest);

            // Automatically offer freed seat to the next waitlist candidate
            if ($booking && $booking->event) {
                $this->waitlistService->offerNextInWaitlist($booking->event);
            }

            activity()
                ->performedOn($refundRequest)
                ->causedBy($reviewer)
                ->withProperties(['credit_note_id' => $creditNote->id])
                ->log('Event refund approved and seat freed for waitlist rotation');

            return $creditNote;
        });
    }

    /**
     * Finance Manager rejects a refund request.
     */
    public function reject(RefundRequest $refundRequest, User $reviewer, string $notes): void
    {
        $refundRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        activity()
            ->performedOn($refundRequest)
            ->causedBy($reviewer)
            ->withProperties(['notes' => $notes])
            ->log('Event refund request rejected');
    }
}
