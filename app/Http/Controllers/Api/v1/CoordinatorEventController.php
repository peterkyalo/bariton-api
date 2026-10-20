<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventBookingResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventBooking;
use App\Services\EventBookingService;
use App\Services\EventReminderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class CoordinatorEventController extends Controller
{
    public function __construct(
        protected EventBookingService $bookingService,
        protected EventReminderService $reminderService
    ) {}

    /**
     * List all events managed by the coordinator.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = Event::withCount([
            'bookings as confirmed_count' => fn ($q) => $q->whereIn('status', ['confirmed', 'attended']),
            'bookings as attended_count' => fn ($q) => $q->where('status', 'attended'),
            'waitlists as waiting_count' => fn ($q) => $q->where('status', 'waiting'),
        ])->orderBy('start_date', 'asc')->get();

        return EventResource::collection($events);
    }

    /**
     * Create a new event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'sometimes|string|in:bootcamp,workshop,masterclass',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'venue' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'capacity' => 'required|integer|min:1|max:1000',
            'training_fee_kes' => 'required|integer|min:0',
            'has_accommodation' => 'required|boolean',
            'accommodation_fee_kes' => 'nullable|integer|min:0',
            'status' => 'required|string|in:draft,published,completed,cancelled',
        ]);

        $baseSlug = Str::slug($validated['title']);
        $slug = $baseSlug;
        $counter = 1;
        while (Event::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $event = Event::create([
            'uuid' => (string) Str::uuid(),
            'type' => $validated['type'] ?? 'bootcamp',
            'title' => $validated['title'],
            'slug' => $slug,
            'description' => $validated['description'],
            'venue' => $validated['venue'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'capacity' => $validated['capacity'],
            'training_fee_kes' => $validated['training_fee_kes'],
            'has_accommodation' => $validated['has_accommodation'],
            'accommodation_fee_kes' => $validated['has_accommodation'] ? ($validated['accommodation_fee_kes'] ?? 0) : 0,
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Event created successfully.',
            'event' => new EventResource($event),
        ], 201);
    }

    /**
     * Show event details with attendees count.
     */
    public function show(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        return response()->json([
            'event' => new EventResource($event),
        ]);
    }

    /**
     * Update an event.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'type' => 'sometimes|string|in:bootcamp,workshop,masterclass',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'venue' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'capacity' => 'sometimes|integer|min:1|max:1000',
            'training_fee_kes' => 'sometimes|integer|min:0',
            'has_accommodation' => 'sometimes|boolean',
            'accommodation_fee_kes' => 'nullable|integer|min:0',
            'status' => 'sometimes|string|in:draft,published,completed,cancelled',
        ]);

        $event->update($validated);

        return response()->json([
            'message' => 'Event updated successfully.',
            'event' => new EventResource($event->fresh()),
        ]);
    }

    /**
     * List all bookings/attendees for an event with accommodation and status filters.
     */
    public function attendees(Request $request, int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $query = EventBooking::with(['user', 'order'])
            ->where('event_id', $event->id);

        if ($request->has('booking_type')) {
            $query->where('booking_type', $request->query('booking_type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $attendees = $query->orderBy('created_at', 'asc')->get();

        $stats = [
            'total_bookings' => $attendees->count(),
            'confirmed' => $attendees->where('status', 'confirmed')->count(),
            'attended' => $attendees->where('status', 'attended')->count(),
            'training_only' => $attendees->where('booking_type', 'training_only')->count(),
            'with_accommodation' => $attendees->where('booking_type', 'training_and_accommodation')->count(),
        ];

        return response()->json([
            'stats' => $stats,
            'attendees' => EventBookingResource::collection($attendees),
        ]);
    }

    /**
     * Check in an attendee on event day.
     */
    public function checkIn(Request $request, string $uuid): JsonResponse
    {
        $booking = EventBooking::where('uuid', $uuid)->firstOrFail();

        try {
            $updated = $this->bookingService->checkInAttendee($booking, $request->user());

            return response()->json([
                'message' => "Attendee {$updated->user->name} successfully checked in.",
                'booking' => new EventBookingResource($updated),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Trigger batch email + SMS reminders to confirmed attendees.
     */
    public function sendReminders(Request $request, int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $stats = $this->reminderService->sendRemindersForEvent($event);
        $count = $stats['attendees_count'];

        return response()->json([
            'message' => "Successfully dispatched reminders to {$count} confirmed attendees.",
            'dispatched_count' => $count,
            'stats' => $stats,
        ]);
    }
}
