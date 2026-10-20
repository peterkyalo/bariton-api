<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Services\ExamGradingService;
use App\Services\MediaStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstructorExamGradingController extends Controller
{
    public function __construct(
        protected ExamGradingService $examGradingService,
        protected MediaStreamService $mediaStreamService
    ) {}

    /**
     * List exam attempts awaiting manual instructor evaluation.
     */
    public function queue(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ExamAttempt::where('status', 'grading')
            ->with(['exam.course', 'user', 'enrollment'])
            ->orderBy('submitted_at');

        // If instructor, filter by courses assigned
        if ($user->hasRole('instructor') && ! $user->hasRole('admin')) {
            $courseIds = $user->taughtCourses()->pluck('courses.id');
            $query->whereHas('exam', function ($q) use ($courseIds) {
                $q->whereIn('course_id', $courseIds);
            });
        }

        $attempts = $query->paginate(20)->through(function ($att) {
            return [
                'uuid' => $att->uuid,
                'exam_title' => $att->exam->title,
                'course_title' => $att->exam->course->title,
                'student_name' => $att->user->name,
                'student_email' => $att->user->email,
                'attempt_number' => $att->attempt_number,
                'status' => $att->status,
                'submitted_at' => $att->submitted_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $attempts,
        ]);
    }

    /**
     * Show attempt details with questions, rubric criteria and media stream URLs for grading.
     */
    public function show(string $attemptUuid, Request $request): JsonResponse
    {
        $attempt = ExamAttempt::where('uuid', $attemptUuid)
            ->with([
                'exam.course',
                'user',
                'enrollment',
                'answers.question.rubric.criteria.levels',
                'answers.rubricScores',
            ])
            ->firstOrFail();

        $questionsData = $attempt->exam->questions->map(function ($q) use ($attempt) {
            $answer = $attempt->answers->firstWhere('exam_question_id', $q->id);

            $streamUrl = null;
            if ($answer && $answer->media_path) {
                // Generate signed stream URL for video or audio performance
                $streamUrl = $this->mediaStreamService->generateSignedLessonStreamUrl(
                    new class($answer->media_path) {
                        public function __construct(public string $media_path) {}
                        public string $uuid = 'sub';
                    }
                );
            }

            return [
                'uuid' => $q->uuid,
                'question_type' => $q->question_type,
                'prompt' => $q->prompt,
                'points' => $q->points,
                'order' => $q->order,
                'options' => $q->options,
                'correct_answer' => $q->correct_answer,
                'rubric' => $q->rubric ? [
                    'uuid' => $q->rubric->uuid,
                    'title' => $q->rubric->title,
                    'criteria' => $q->rubric->criteria->map(function ($crit) {
                        return [
                            'id' => $crit->id,
                            'title' => $crit->title,
                            'description' => $crit->description,
                            'max_points' => $crit->max_points,
                            'order' => $crit->order,
                            'levels' => $crit->levels->map(function ($lvl) {
                                return [
                                    'id' => $lvl->id,
                                    'title' => $lvl->title,
                                    'points' => $lvl->points,
                                    'description' => $lvl->description,
                                ];
                            }),
                        ];
                    }),
                ] : null,
                'answer' => $answer ? [
                    'uuid' => $answer->uuid,
                    'selected_option' => $answer->selected_option,
                    'text_answer' => $answer->text_answer,
                    'media_path' => $answer->media_path,
                    'media_mime' => $answer->media_mime,
                    'media_stream_url' => $streamUrl,
                    'is_auto_graded' => $answer->is_auto_graded,
                    'score_awarded' => $answer->score_awarded,
                    'feedback' => $answer->feedback,
                    'rubric_scores' => $answer->rubricScores->map(function ($rs) {
                        return [
                            'rubric_criterion_id' => $rs->rubric_criterion_id,
                            'rubric_level_id' => $rs->rubric_level_id,
                            'points_awarded' => $rs->points_awarded,
                            'comments' => $rs->comments,
                        ];
                    }),
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'attempt' => [
                    'uuid' => $attempt->uuid,
                    'attempt_number' => $attempt->attempt_number,
                    'status' => $attempt->status,
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                    'total_score' => $attempt->total_score,
                    'max_score' => $attempt->max_score,
                    'score_percent' => $attempt->score_percent,
                    'passed' => $attempt->passed,
                    'overall_feedback' => $attempt->overall_feedback,
                    'exam_pass_mark_percent' => $attempt->enrollment->exam_pass_mark_percent ?: 70,
                ],
                'course' => [
                    'uuid' => $attempt->exam->course->uuid,
                    'title' => $attempt->exam->course->title,
                ],
                'student' => [
                    'name' => $attempt->user->name,
                    'email' => $attempt->user->email,
                ],
                'questions' => $questionsData,
            ],
        ]);
    }

    /**
     * Submit grading evaluations and release examination result.
     */
    public function grade(string $attemptUuid, Request $request): JsonResponse
    {
        $user = $request->user();

        $attempt = ExamAttempt::where('uuid', $attemptUuid)->firstOrFail();

        $request->validate([
            'evaluations' => ['required', 'array'],
            'evaluations.*.answer_uuid' => ['required', 'string'],
            'evaluations.*.score_awarded' => ['nullable', 'numeric', 'min:0'],
            'evaluations.*.feedback' => ['nullable', 'string'],
            'evaluations.*.rubric_scores' => ['nullable', 'array'],
            'overall_feedback' => ['nullable', 'string'],
        ]);

        $gradedAttempt = $this->examGradingService->gradeAttempt(
            $attempt,
            $user,
            $request->input('evaluations'),
            $request->input('overall_feedback')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Examination graded successfully.',
            'data' => [
                'attempt' => [
                    'uuid' => $gradedAttempt->uuid,
                    'status' => $gradedAttempt->status,
                    'total_score' => $gradedAttempt->total_score,
                    'max_score' => $gradedAttempt->max_score,
                    'score_percent' => $gradedAttempt->score_percent,
                    'passed' => $gradedAttempt->passed,
                    'graded_at' => $gradedAttempt->graded_at?->toIso8601String(),
                ],
            ],
        ]);
    }
}
