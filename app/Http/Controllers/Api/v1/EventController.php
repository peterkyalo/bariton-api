<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventBookingResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventWaitlistResource;
use App\Models\Event;
use App\Services\EventBookingService;
use App\Services\EventWaitlistService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(
        protected EventBookingService $bookingService,
        protected EventWaitlistService $waitlistService
    ) {}

    /**
     * Public list of published upcoming events.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = Event::where('status', 'published')
            ->orderBy('start_date', 'asc')
            ->get();

        return EventResource::collection($events);
    }

    /**
     * Public event details by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        return response()->json([
            'event' => new EventResource($event),
        ]);
    }

    /**
     * Authenticated student books an event seat (online hold).
     */
    public function book(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'booking_type' => 'required|string|in:training_only,training_and_accommodation',
        ]);

        if ($validated['booking_type'] === 'training_and_accommodation' && ! $event->has_accommodation) {
            return response()->json([
                'message' => 'Accommodation is not offered for this event.',
            ], 422);
        }

        try {
            $result = $this->bookingService->createOnlineHold(
                $event,
                $request->user(),
                $validated['booking_type']
            );

            return response()->json([
                'message' => 'Seat held successfully. Please complete payment within the hold duration.',
                'booking' => new EventBookingResource($result['booking']),
                'order' => $result['order'],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'is_full' => $event->isFull(),
            ], 422);
        }
    }

    /**
     * Authenticated student joins the waitlist for a full event.
     */
    public function waitlist(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'booking_type' => 'required|string|in:training_only,training_and_accommodation',
        ]);

        try {
            $entry = $this->waitlistService->joinWaitlist(
                $event,
                $request->user(),
                $validated['booking_type']
            );

            return response()->json([
                'message' => 'You have joined the waitlist. You will be automatically notified if a seat becomes available.',
                'waitlist' => new EventWaitlistResource($entry),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
