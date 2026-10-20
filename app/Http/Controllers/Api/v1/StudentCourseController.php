<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordLessonProgressRequest;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\LessonResource;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\CourseProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentCourseController extends Controller
{
    public function __construct(
        protected CourseProgressService $progressService
    ) {}

    /**
     * List all enrollments for the authenticated student.
     */
    public function enrollments(Request $request): AnonymousResourceCollection
    {
        $enrollments = $request->user()->enrollments()
            ->with([
                'course.instructors',
                'course.modules.lessons',
                'enrollmentModules',
                'lessonProgress',
            ])
            ->latest()
            ->paginate($request->input('per_page', 10));

        return EnrollmentResource::collection($enrollments);
    }

    /**
     * Full course curriculum tree for the student player.
     */
    public function enrollmentDetail(Request $request, string $uuid): JsonResponse
    {
        $enrollment = $request->user()->enrollments()
            ->where('uuid', $uuid)
            ->with([
                'course.instructors',
                'course.modules.lessons',
                'enrollmentModules',
                'lessonProgress',
            ])
            ->firstOrFail();

        return response()->json([
            'enrollment' => new EnrollmentResource($enrollment),
        ]);
    }

    /**
     * Get specific lesson for learning player.
     */
    public function lessonDetail(Request $request, string $uuid): JsonResponse
    {
        $lesson = Lesson::where('uuid', $uuid)->with('module.course')->firstOrFail();

        $this->authorize('view', $lesson);

        return response()->json([
            'lesson' => new LessonResource($lesson),
        ]);
    }

    /**
     * Record lesson progress (YouTube 90% watched, Audio 90% played, PDF marked complete).
     */
    public function recordProgress(RecordLessonProgressRequest $request, string $uuid): JsonResponse
    {
        $lesson = Lesson::where('uuid', $uuid)->with('module.course')->firstOrFail();

        $this->authorize('view', $lesson);

        $enrollment = $request->user()->enrollments()
            ->where('course_id', $lesson->module->course_id)
            ->firstOrFail();

        $progress = $this->progressService->recordProgress(
            $enrollment,
            $lesson,
            (int) $request->input('progress_percent'),
            (int) $request->input('position_seconds', 0),
            (bool) $request->input('mark_complete', false)
        );

        $enrollment->refresh();

        return response()->json([
            'message' => 'Lesson progress updated successfully.',
            'lesson_id' => $lesson->id,
            'lesson_status' => $progress->status,
            'lesson_progress_percent' => $progress->progress_percent,
            'course_progress_percent' => (float) $enrollment->progress_percent,
            'course_status' => $enrollment->status,
            'completed_at' => $progress->completed_at?->toISOString(),
        ]);
    }
}
