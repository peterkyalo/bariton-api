<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\CertificateService;
use App\Services\ExamService;
use App\Services\MediaStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentExamController extends Controller
{
    public function __construct(
        protected ExamService $examService,
        protected CertificateService $certificateService,
        protected MediaStreamService $mediaStreamService
    ) {}

    /**
     * Show final exam details, student eligibility, attempts count, and past attempts.
     */
    public function showCourseExam(string $courseSlugOrUuid, Request $request): JsonResponse
    {
        $user = $request->user();

        $course = Course::where('slug', $courseSlugOrUuid)
            ->orWhere('uuid', $courseSlugOrUuid)
            ->with(['exam.questions'])
            ->firstOrFail();

        $exam = $course->exam;
        if (! $exam) {
            return response()->json([
                'status' => 'error',
                'message' => 'No final examination has been configured for this course yet.',
            ], 404);
        }

        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (! $enrollment) {
            return response()->json([
                'status' => 'error',
                'message' => 'You must be actively enrolled in this course to view examination details.',
            ], 403);
        }

        $canAttemptInfo = $this->examService->canAttempt($user, $exam);

        // Fetch past attempts
        $pastAttempts = ExamAttempt::where('exam_id', $exam->id)
            ->where('enrollment_id', $enrollment->id)
            ->orderBy('attempt_number')
            ->get()
            ->map(function ($att) {
                return [
                    'uuid' => $att->uuid,
                    'attempt_number' => $att->attempt_number,
                    'status' => $att->status,
                    'started_at' => $att->started_at?->toIso8601String(),
                    'submitted_at' => $att->submitted_at?->toIso8601String(),
                    'total_score' => $att->total_score,
                    'max_score' => $att->max_score,
                    'score_percent' => $att->score_percent,
                    'passed' => $att->passed,
                    'overall_feedback' => $att->overall_feedback,
                ];
            });

        // Check if certificate already exists
        $certificate = Certificate::where('enrollment_id', $enrollment->id)
            ->where('status', 'issued')
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'exam' => [
                    'uuid' => $exam->uuid,
                    'title' => $exam->title,
                    'description' => $exam->description,
                    'duration_minutes' => $exam->duration_minutes,
                    'instructions' => $exam->instructions,
                    'questions_count' => $exam->questions->count(),
                    'total_points' => $exam->questions->sum('points'),
                    'pass_mark_percent' => $enrollment->exam_pass_mark_percent ?: 70,
                ],
                'course' => [
                    'uuid' => $course->uuid,
                    'title' => $course->title,
                    'slug' => $course->slug,
                ],
                'eligibility' => $canAttemptInfo,
                'past_attempts' => $pastAttempts,
                'certificate' => $certificate ? [
                    'uuid' => $certificate->uuid,
                    'certificate_code' => $certificate->certificate_code,
                    'holder_name' => $certificate->holder_name,
                    'status' => $certificate->status,
                    'issued_at' => $certificate->issued_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    /**
     * Start a new exam attempt (or resume active attempt).
     */
    public function start(string $examUuid, Request $request): JsonResponse
    {
        $user = $request->user();
        $exam = Exam::where('uuid', $examUuid)->firstOrFail();

        $attempt = $this->examService->startAttempt($user, $exam);

        return $this->formatAttemptResponse($attempt, 'Exam attempt started successfully.');
    }

    /**
     * View exam attempt questions and state.
     */
    public function showAttempt(string $attemptUuid, Request $request): JsonResponse
    {
        $user = $request->user();
        $attempt = ExamAttempt::where('uuid', $attemptUuid)
            ->where('user_id', $user->id)
            ->with(['exam.questions', 'answers'])
            ->firstOrFail();

        // If in_progress and time has expired, auto-submit
        if ($attempt->status === 'in_progress') {
            $effectiveExpiresAt = $attempt->upload_grace_period_expires_at ?? $attempt->expires_at;
            if (now()->greaterThan($effectiveExpiresAt)) {
                $attempt = $this->examService->submitAttempt($attempt);
            }
        }

        return $this->formatAttemptResponse($attempt);
    }

    /**
     * Auto-save answers with debounce while typing.
     */
    public function saveAnswers(string $attemptUuid, Request $request): JsonResponse
    {
        $user = $request->user();
        $attempt = ExamAttempt::where('uuid', $attemptUuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $answers = $request->input('answers', []);
        $result = $this->examService->saveAnswers($attempt, $answers);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Stop timer on recorded performance question, initiating 15-minute grace period.
     */
    public function recordStop(string $attemptUuid, Request $request): JsonResponse
    {
        $user = $request->user();
        $attempt = ExamAttempt::where('uuid', $attemptUuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $request->validate([
            'question_uuid' => ['required', 'string'],
            'media_path' => ['nullable', 'string'],
            'media_mime' => ['nullable', 'string'],
        ]);

        $attempt = $this->examService->recordPerformanceCaptured(
            $attempt,
            $request->input('question_uuid'),
            $request->input('media_path'),
            $request->input('media_mime')
        );

        return $this->formatAttemptResponse($attempt, 'Timer paused for recorded performance. 15-minute upload grace period active.');
    }

    /**
     * Submit exam attempt.
     */
    public function submit(string $attemptUuid, Request $request): JsonResponse
    {
        $user = $request->user();
        $attempt = ExamAttempt::where('uuid', $attemptUuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // If answers provided in payload, save them first
        if ($request->has('answers')) {
            $this->examService->saveAnswers($attempt, $request->input('answers'));
            $attempt->refresh();
        }

        $submittedAttempt = $this->examService->submitAttempt($attempt);

        return $this->formatAttemptResponse($submittedAttempt, 'Examination submitted successfully.');
    }

    /**
     * List all certificates belonging to the authenticated student.
     */
    public function certificates(Request $request): JsonResponse
    {
        $user = $request->user();

        $certificates = Certificate::where('user_id', $user->id)
            ->with('course')
            ->orderByDesc('issued_at')
            ->get()
            ->map(function ($cert) {
                return [
                    'uuid' => $cert->uuid,
                    'certificate_code' => $cert->certificate_code,
                    'holder_name' => $cert->holder_name,
                    'course_title' => $cert->course_title,
                    'path_type' => $cert->path_type,
                    'status' => $cert->status,
                    'issued_at' => $cert->issued_at?->toIso8601String(),
                    'reissue_replaces_code' => $cert->reissue_replaces_code,
                    'reissue_replaced_by_code' => $cert->reissue_replaced_by_code,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $certificates,
        ]);
    }

    /**
     * Download student certificate PDF.
     */
    public function downloadCertificate(string $uuid, Request $request): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();

        $certificate = Certificate::where('uuid', $uuid)->firstOrFail();

        // Authorization: owner or admin
        if ($certificate->user_id !== $user->id && ! $user->hasRole('admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to download this certificate.',
            ], 403);
        }

        $disk = $certificate->pdf_disk ?: 'local';
        if (! Storage::disk($disk)->exists($certificate->pdf_path)) {
            // Re-render PDF if missing
            $this->certificateService->renderAndSavePdf($certificate);
        }

        $fullPath = Storage::disk($disk)->path($certificate->pdf_path);

        return response()->download($fullPath, "Certificate-{$certificate->certificate_code}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Helper to format attempt state and sanitize question outputs.
     */
    protected function formatAttemptResponse(ExamAttempt $attempt, ?string $message = null): JsonResponse
    {
        $attempt->load(['exam.questions', 'answers.question']);

        $now = now();
        $isGracePeriod = $attempt->recording_stopped_at !== null && $attempt->upload_grace_period_expires_at !== null;
        $effectiveExpiresAt = $attempt->upload_grace_period_expires_at ?? $attempt->expires_at;
        $remainingSeconds = max(0, $now->diffInSeconds($effectiveExpiresAt, false));

        $questionsData = $attempt->exam->questions->map(function ($q) use ($attempt) {
            $answer = $attempt->answers->firstWhere('exam_question_id', $q->id);

            $data = [
                'uuid' => $q->uuid,
                'question_type' => $q->question_type,
                'prompt' => $q->prompt,
                'points' => $q->points,
                'order' => $q->order,
                'options' => $q->options,
                'user_answer' => [
                    'selected_option' => $answer?->selected_option,
                    'text_answer' => $answer?->text_answer,
                    'media_path' => $answer?->media_path,
                ],
            ];

            // If exam is graded, expose score and feedback
            if ($attempt->status === 'graded') {
                $data['score_awarded'] = $answer?->score_awarded;
                $data['feedback'] = $answer?->feedback;
                if ($q->question_type === 'multiple_choice') {
                    $data['correct_answer'] = $q->correct_answer;
                    $data['explanation'] = $q->explanation;
                }
            }

            return $data;
        });

        // Check if certificate was issued for this attempt
        $certificate = null;
        if ($attempt->passed) {
            $cert = Certificate::where('enrollment_id', $attempt->enrollment_id)
                ->where('status', 'issued')
                ->first();
            if ($cert) {
                $certificate = [
                    'uuid' => $cert->uuid,
                    'certificate_code' => $cert->certificate_code,
                    'holder_name' => $cert->holder_name,
                    'status' => $cert->status,
                    'issued_at' => $cert->issued_at?->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'attempt' => [
                    'uuid' => $attempt->uuid,
                    'attempt_number' => $attempt->attempt_number,
                    'status' => $attempt->status,
                    'started_at' => $attempt->started_at?->toIso8601String(),
                    'expires_at' => $attempt->expires_at?->toIso8601String(),
                    'recording_stopped_at' => $attempt->recording_stopped_at?->toIso8601String(),
                    'upload_grace_period_expires_at' => $attempt->upload_grace_period_expires_at?->toIso8601String(),
                    'is_grace_period' => $isGracePeriod,
                    'remaining_seconds' => $remainingSeconds,
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                    'total_score' => $attempt->total_score,
                    'max_score' => $attempt->max_score,
                    'score_percent' => $attempt->score_percent,
                    'passed' => $attempt->passed,
                    'overall_feedback' => $attempt->overall_feedback,
                    'certificate' => $certificate,
                ],
                'questions' => $questionsData,
            ],
        ]);
    }
}
