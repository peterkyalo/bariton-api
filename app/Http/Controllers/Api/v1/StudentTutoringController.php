<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentTutoringPackageResource;
use App\Http\Resources\TutoringSessionResource;
use App\Models\Course;
use App\Models\StudentTutoringPackage;
use App\Models\TutoringSession;
use App\Models\User;
use App\Services\TutoringBookingService;
use App\Services\TutoringSlotService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentTutoringController extends Controller
{
    public function __construct(
        protected TutoringSlotService $slotService,
        protected TutoringBookingService $bookingService
    ) {}

    /**
     * List all tutoring packages owned by the student with remaining credits.
     */
    public function myPackages(Request $request): AnonymousResourceCollection
    {
        $packages = StudentTutoringPackage::with(['tutoringPackage', 'course', 'instructor'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return StudentTutoringPackageResource::collection($packages);
    }

    /**
     * Get available booking slots for an instructor within a date range.
     */
    public function getAvailableSlots(Request $request, int $instructorId): JsonResponse
    {
        $instructor = User::role('instructor')->findOrFail($instructorId);

        $startDate = $request->query('start_date', now()->toDateString());
        $endDate = $request->query('end_date', now()->addDays(14)->toDateString());

        if ($request->filled('date')) {
            $startDate = $request->query('date');
            $endDate = $request->query('date');
        }

        $slots = $this->slotService->getAvailableSlots($instructor, $startDate, $endDate);

        return response()->json([
            'instructor' => [
                'id' => $instructor->id,
                'name' => $instructor->name,
                'email' => $instructor->email,
            ],
            'slots' => $slots,
        ]);
    }

    /**
     * Get list of active instructors available for private tutoring.
     */
    public function getInstructors(): JsonResponse
    {
        $instructors = User::role('instructor')
            ->where('status', 'active')
            ->select(['id', 'name', 'email'])
            ->get();

        return response()->json([
            'data' => $instructors,
        ]);
    }

    /**
     * Book a tutoring session from available credits.
     */
    public function book(Request $request): JsonResponse
    {
        if (! $request->filled('student_package_uuid') && $request->filled('student_tutoring_package_id')) {
            $sp = StudentTutoringPackage::where('id', $request->input('student_tutoring_package_id'))
                ->where('user_id', $request->user()->id)
                ->first();
            if ($sp) {
                $request->merge(['student_package_uuid' => $sp->uuid]);
            }
        }

        $validated = $request->validate([
            'student_package_uuid' => 'required|exists:student_tutoring_packages,uuid',
            'instructor_id' => 'required|exists:users,id',
            'scheduled_start' => 'required|date|after:now',
            'mode' => 'required|string|in:zoom,google_meet,in_person',
            'course_id' => 'nullable|exists:courses,id',
        ]);

        $studentPackage = StudentTutoringPackage::where('uuid', $validated['student_package_uuid'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $instructor = User::findOrFail($validated['instructor_id']);
        $course = ! empty($validated['course_id']) ? Course::find($validated['course_id']) : null;

        try {
            $session = $this->bookingService->bookSession(
                $request->user(),
                $studentPackage,
                $instructor,
                $validated['scheduled_start'],
                $validated['mode'],
                null,
                null,
                $course
            );

            return response()->json([
                'message' => 'Tutoring session booked successfully.',
                'session' => new TutoringSessionResource($session->fresh(['student', 'instructor', 'course'])),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List all tutoring sessions for the student.
     */
    public function mySessions(Request $request): AnonymousResourceCollection
    {
        $query = TutoringSession::with(['instructor', 'course', 'studentTutoringPackage'])
            ->where('student_id', $request->user()->id);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $sessions = $query->orderBy('scheduled_start', 'asc')->get();

        return TutoringSessionResource::collection($sessions);
    }

    /**
     * Reschedule a session with advance notice check or emergency waiver.
     */
    public function reschedule(Request $request, string $uuid): JsonResponse
    {
        $session = TutoringSession::where('uuid', $uuid)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'new_scheduled_start' => 'required|date|after:now',
            'new_mode' => 'nullable|string|in:zoom,google_meet,in_person',
            'waive_notice' => 'sometimes|boolean',
            'waiver_reason' => 'required_if:waive_notice,true|nullable|string|min:5|max:500',
        ]);

        try {
            $updated = $this->bookingService->rescheduleSession(
                $session,
                $request->user(),
                $validated['new_scheduled_start'],
                $validated['new_mode'] ?? null,
                $validated['waive_notice'] ?? false,
                $validated['waiver_reason'] ?? null
            );

            return response()->json([
                'message' => 'Session rescheduled successfully.',
                'session' => new TutoringSessionResource($updated->fresh(['instructor', 'course'])),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
