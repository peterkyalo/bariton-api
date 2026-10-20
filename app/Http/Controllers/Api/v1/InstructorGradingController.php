<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Services\Grading\AssignmentGradingService;
use App\Services\MediaStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstructorGradingController extends Controller
{
    public function __construct(
        protected AssignmentGradingService $gradingService,
        protected MediaStreamService $mediaStreamService
    ) {}

    /**
     * Instructor grading queue listing submissions awaiting review or completed.
     */
    public function queue(Request $request): JsonResponse
    {
        $this->authorizeInstructor();
        $user = $request->user();

        $query = AssignmentSubmission::query()
            ->with(['assignment.module.course', 'student', 'rubricScores.criterion', 'rubricScores.level'])
            ->latest();

        // Scope to instructor's courses unless admin
        if (! $user->can('courses.manage-all')) {
            $courseIds = $user->coursesTeaching()->pluck('courses.id')->toArray();
            $query->whereHas('assignment.module', function ($q) use ($courseIds) {
                $q->whereIn('course_id', $courseIds);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('course_id')) {
            $query->whereHas('assignment.module', function ($q) use ($request) {
                $q->where('course_id', $request->input('course_id'));
            });
        }

        $submissions = $query->paginate($request->input('per_page', 15));

        return response()->json($submissions);
    }

    /**
     * Show submission details, rubric evaluation sheet, and signed media streaming URL.
     */
    public function show(Request $request, string $submissionUuid): JsonResponse
    {
        $this->authorizeInstructor();

        $submission = AssignmentSubmission::where('uuid', $submissionUuid)
            ->with([
                'assignment.module.course',
                'assignment.rubric.criteria.levels',
                'student',
                'enrollment',
                'rubricScores.criterion',
                'rubricScores.level',
                'grader',
            ])
            ->firstOrFail();

        // If newly opened and was in submitted status, transition to grading
        if ($submission->status === 'submitted') {
            $submission->update(['status' => 'grading']);
        }

        $streamUrl = $this->mediaStreamService->generateSignedSubmissionStreamUrl($submission->uuid, 120);

        return response()->json([
            'submission' => $submission,
            'stream_url' => $streamUrl,
            'rubric' => $submission->assignment->rubric,
            'pass_mark_percent' => $submission->enrollment->assignment_pass_mark_percent ?? 70,
        ]);
    }

    /**
     * Grade an assignment submission using the rubric template.
     */
    public function grade(Request $request, string $submissionUuid): JsonResponse
    {
        $this->authorizeInstructor();

        $submission = AssignmentSubmission::where('uuid', $submissionUuid)
            ->with(['assignment.rubric.criteria.levels', 'enrollment'])
            ->firstOrFail();

        $validated = $request->validate([
            'rubric_scores' => ['nullable', 'array'],
            'rubric_scores.*.criterion_id' => ['required_with:rubric_scores', 'integer'],
            'rubric_scores.*.level_id' => ['required_with:rubric_scores', 'integer'],
            'rubric_scores.*.comments' => ['nullable', 'string', 'max:500'],
            'feedback' => ['required', 'string', 'min:5', 'max:5000'],
            'total_points_earned' => ['nullable', 'integer'],
            'total_points_possible' => ['nullable', 'integer'],
        ]);

        $graded = $this->gradingService->grade($submission, $request->user(), $validated);

        return response()->json([
            'message' => $graded->passed
                ? 'Submission graded successfully: Passed.'
                : 'Submission graded: Mark below pass threshold; student requested to resubmit.',
            'submission' => $graded,
        ]);
    }

    protected function authorizeInstructor(): void
    {
        if (! request()->user() || (! request()->user()->can('courses.teach') && ! request()->user()->can('courses.manage-all'))) {
            abort(403, 'Unauthorized. Instructor access required.');
        }
    }
}
