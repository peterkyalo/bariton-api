<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventBookingResource;
use App\Models\Event;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\EventBookingService;
use App\Services\EventRefundService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceEventController extends Controller
{
    public function __construct(
        protected EventBookingService $bookingService,
        protected EventRefundService $refundService
    ) {}

    /**
     * Finance staff creates a 48-hour cash hold for an in-person attendee.
     */
    public function createCashHold(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'attendee_id' => 'required|exists:users,id',
            'booking_type' => 'required|string|in:training_only,training_and_accommodation',
        ]);

        $attendee = User::findOrFail($validated['attendee_id']);

        try {
            $result = $this->bookingService->createCashHold(
                $event,
                $attendee,
                $request->user(),
                $validated['booking_type']
            );

            return response()->json([
                'message' => 'Cash hold placed successfully. 48-hour window active for in-person payment.',
                'booking' => new EventBookingResource($result['booking']),
                'order' => $result['order'],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List physical event refund requests.
     */
    public function refunds(Request $request): JsonResponse
    {
        $query = RefundRequest::with(['user', 'order', 'eventBooking.event'])
            ->whereNotNull('event_booking_id');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $refunds = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'refund_requests' => $refunds,
        ]);
    }

    /**
     * Finance Manager approves event refund request: issues Credit Note and auto-rotates waitlist.
     */
    public function approveRefund(Request $request, string $uuid): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'finance_manager']), 403, 'Only finance managers can review refunds.');
        $refundRequest = RefundRequest::where('uuid', $uuid)
            ->whereNotNull('event_booking_id')
            ->firstOrFail();

        if ($refundRequest->status !== 'pending') {
            return response()->json([
                'message' => "Refund request is already {$refundRequest->status}.",
            ], 422);
        }

        $validated = $request->validate([
            'manual_reference' => 'nullable|string|max:100',
        ]);

        try {
            $creditNote = $this->refundService->approve(
                $refundRequest,
                $request->user(),
                $validated['manual_reference'] ?? null
            );

            return response()->json([
                'message' => 'Event refund approved successfully. Official credit note issued and seat released to waitlist.',
                'credit_note' => $creditNote,
                'refund_request' => $refundRequest->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Finance Manager rejects event refund request.
     */
    public function rejectRefund(Request $request, string $uuid): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'finance_manager']), 403, 'Only finance managers can review refunds.');
        $refundRequest = RefundRequest::where('uuid', $uuid)
            ->whereNotNull('event_booking_id')
            ->firstOrFail();

        if ($refundRequest->status !== 'pending') {
            return response()->json([
                'message' => "Refund request is already {$refundRequest->status}.",
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $this->refundService->reject($refundRequest, $request->user(), $validated['reason']);

        return response()->json([
            'message' => 'Event refund request rejected.',
            'refund_request' => $refundRequest->fresh(),
        ]);
    }
}
