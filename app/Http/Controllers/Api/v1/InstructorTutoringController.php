<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TutoringSessionResource;
use App\Models\InstructorAvailability;
use App\Models\InstructorBlockedDate;
use App\Models\TutoringSession;
use App\Services\TutoringBookingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstructorTutoringController extends Controller
{
    public function __construct(
        protected TutoringBookingService $bookingService
    ) {}

    /**
     * Get instructor's weekly recurring availability.
     */
    public function getAvailability(Request $request): JsonResponse
    {
        $availabilities = InstructorAvailability::where('instructor_id', $request->user()->id)
            ->orderBy('day_of_week', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json([
            'availabilities' => $availabilities,
        ]);
    }

    /**
     * Set instructor's weekly recurring availability shifts.
     */
    public function setAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shifts' => 'required|array',
            'shifts.*.day_of_week' => 'required|integer|min:0|max:6',
            'shifts.*.start_time' => 'required|date_format:H:i',
            'shifts.*.end_time' => 'required|date_format:H:i|after:shifts.*.start_time',
            'shifts.*.is_active' => 'sometimes|boolean',
        ]);

        $instructorId = $request->user()->id;

        // Replace current shifts
        InstructorAvailability::where('instructor_id', $instructorId)->delete();

        foreach ($validated['shifts'] as $shift) {
            InstructorAvailability::create([
                'instructor_id' => $instructorId,
                'day_of_week' => $shift['day_of_week'],
                'start_time' => $shift['start_time'],
                'end_time' => $shift['end_time'],
                'is_active' => $shift['is_active'] ?? true,
            ]);
        }

        return response()->json([
            'message' => 'Weekly tutoring availability updated successfully.',
            'availabilities' => InstructorAvailability::where('instructor_id', $instructorId)->get(),
        ]);
    }

    /**
     * Get instructor's blocked dates (time-off/blackouts).
     */
    public function getBlockedDates(Request $request): JsonResponse
    {
        $blockedDates = InstructorBlockedDate::where('instructor_id', $request->user()->id)
            ->orderBy('start_date', 'asc')
            ->get();

        return response()->json([
            'blocked_dates' => $blockedDates,
        ]);
    }

    /**
     * Add blackout / time-off date range.
     */
    public function addBlockedDate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
        ]);

        $blocked = InstructorBlockedDate::create([
            'instructor_id' => $request->user()->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'Blocked date range added successfully.',
            'blocked_date' => $blocked,
        ], 201);
    }

    /**
     * Remove blackout date.
     */
    public function deleteBlockedDate(Request $request, int $id): JsonResponse
    {
        $blocked = InstructorBlockedDate::where('id', $id)
            ->where('instructor_id', $request->user()->id)
            ->firstOrFail();

        $blocked->delete();

        return response()->json([
            'message' => 'Blocked date range removed.',
        ]);
    }

    /**
     * Instructor's booked sessions roster.
     */
    public function sessions(Request $request): AnonymousResourceCollection
    {
        $query = TutoringSession::with(['student', 'course', 'studentTutoringPackage'])
            ->where('instructor_id', $request->user()->id);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $sessions = $query->orderBy('scheduled_start', 'asc')->get();

        return TutoringSessionResource::collection($sessions);
    }

    /**
     * Update session details (e.g. meeting link or studio location).
     */
    public function updateSessionDetails(Request $request, string $uuid): JsonResponse
    {
        $session = TutoringSession::where('uuid', $uuid)
            ->where('instructor_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'mode' => 'sometimes|string|in:zoom,google_meet,in_person',
            'meeting_link' => 'nullable|string|url|max:500',
            'location' => 'nullable|string|max:255',
        ]);

        $session->update($validated);

        return response()->json([
            'message' => 'Session details updated successfully.',
            'session' => new TutoringSessionResource($session->fresh(['student', 'course'])),
        ]);
    }

    /**
     * Instructor cancels session and restores credit back to the student.
     */
    public function cancelSession(Request $request, string $uuid): JsonResponse
    {
        $session = TutoringSession::where('uuid', $uuid)
            ->where('instructor_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        try {
            $cancelled = $this->bookingService->cancelByInstructor($session, $request->user(), $validated['reason']);

            return response()->json([
                'message' => 'Session cancelled. Session credit has been returned to the student.',
                'session' => new TutoringSessionResource($cancelled->fresh(['student', 'course'])),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mark session attendance (attended or missed).
     */
    public function markAttendance(Request $request, string $uuid): JsonResponse
    {
        $session = TutoringSession::where('uuid', $uuid)
            ->where('instructor_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'status' => 'required|string|in:attended,missed',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $updated = $this->bookingService->markAttendance(
                $session,
                $request->user(),
                $validated['status'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'message' => "Session marked as {$validated['status']}.",
                'session' => new TutoringSessionResource($updated->fresh(['student', 'course'])),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
