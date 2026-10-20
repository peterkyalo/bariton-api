<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventBookingResource;
use App\Http\Resources\EventWaitlistResource;
use App\Models\EventBooking;
use App\Models\EventWaitlist;
use App\Services\EventRefundService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentEventBookingController extends Controller
{
    public function __construct(
        protected EventRefundService $refundService
    ) {}

    /**
     * List all event bookings for the authenticated student.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $bookings = EventBooking::with(['event', 'order'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return EventBookingResource::collection($bookings);
    }

    /**
     * Show a specific event booking by UUID.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $booking = EventBooking::with(['event', 'order'])
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'booking' => new EventBookingResource($booking),
            'refund_eligibility' => $this->refundService->validateEligibility($booking),
        ]);
    }

    /**
     * Submit a refund request for an event booking with proof document.
     */
    public function requestRefund(Request $request, string $uuid): JsonResponse
    {
        $booking = EventBooking::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
            'proof_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        try {
            $proofFile = $request->file('proof_document');
            $refundRequest = $this->refundService->requestRefund(
                $booking,
                $request->user(),
                $validated['reason'],
                $proofFile
            );

            return response()->json([
                'message' => 'Refund request submitted successfully. It is awaiting finance manager review.',
                'refund_request' => $refundRequest,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List student's waitlist entries.
     */
    public function myWaitlists(Request $request): AnonymousResourceCollection
    {
        $waitlists = EventWaitlist::with(['event'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return EventWaitlistResource::collection($waitlists);
    }
}
