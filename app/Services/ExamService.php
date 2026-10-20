<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamQuestion;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExamService
{
    public function __construct(
        protected CertificateService $certificateService
    ) {}

    /**
     * Check if a student can attempt the specified exam.
     */
    public function canAttempt(User $user, Exam $exam): array
    {
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $exam->course_id)
            ->where('status', 'active')
            ->first();

        if (! $enrollment) {
            return [
                'allowed' => false,
                'reason' => 'You are not actively enrolled in this course.',
            ];
        }

        // Check if student already passed
        $hasPassed = ExamAttempt::where('exam_id', $exam->id)
            ->where('enrollment_id', $enrollment->id)
            ->where('passed', true)
            ->exists();

        if ($hasPassed) {
            return [
                'allowed' => false,
                'reason' => 'You have already successfully passed this examination!',
                'enrollment' => $enrollment,
            ];
        }

        // Check for active in-progress attempt
        $activeAttempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('enrollment_id', $enrollment->id)
            ->where('status', 'in_progress')
            ->first();

        if ($activeAttempt) {
            // Check if server timer has expired
            $effectiveExpiresAt = $activeAttempt->upload_grace_period_expires_at ?? $activeAttempt->expires_at;
            if (now()->greaterThan($effectiveExpiresAt)) {
                $this->submitAttempt($activeAttempt);
            } else {
                return [
                    'allowed' => true,
                    'active_attempt' => $activeAttempt,
                    'enrollment' => $enrollment,
                ];
            }
        }

        // Get past completed attempts
        $pastAttempts = ExamAttempt::where('exam_id', $exam->id)
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('status', ['submitted', 'grading', 'graded'])
            ->orderByDesc('attempt_number')
            ->get();

        $attemptLimit = (int) ($enrollment->exam_attempt_limit ?: 3);
        $attemptsUsed = $pastAttempts->count();

        // Check attempt limit
        if ($attemptsUsed >= $attemptLimit) {
            return [
                'allowed' => false,
                'limit_reached' => true,
                'attempts_used' => $attemptsUsed,
                'attempt_limit' => $attemptLimit,
                'reason' => 'Exam attempt limit reached. Please purchase an exam retake package to unlock additional attempts.',
                'enrollment' => $enrollment,
            ];
        }

        // Check cooling off wait time between attempts
        $lastAttempt = $pastAttempts->first();
        if ($lastAttempt && $lastAttempt->passed === false) {
            $waitHours = (int) ($enrollment->exam_wait_time_hours ?: 24);
            $referenceTime = $lastAttempt->graded_at ?? $lastAttempt->submitted_at ?? $lastAttempt->updated_at;
            $availableAt = $referenceTime->copy()->addHours($waitHours);

            if (now()->lessThan($availableAt)) {
                $hoursRemaining = now()->diffInHours($availableAt, false);
                return [
                    'allowed' => false,
                    'cooling_off' => true,
                    'wait_hours' => $waitHours,
                    'available_at' => $availableAt->toIso8601String(),
                    'reason' => "You must wait {$waitHours} hours between attempts to allow for further study. You can retake this exam after {$availableAt->toFormattedDateString()} {$availableAt->format('H:i')}.",
                    'enrollment' => $enrollment,
                ];
            }
        }

        return [
            'allowed' => true,
            'next_attempt_number' => $attemptsUsed + 1,
            'attempts_used' => $attemptsUsed,
            'attempt_limit' => $attemptLimit,
            'enrollment' => $enrollment,
        ];
    }

    /**
     * Start a new exam attempt with server-enforced timer.
     */
    public function startAttempt(User $user, Exam $exam): ExamAttempt
    {
        $check = $this->canAttempt($user, $exam);

        if (! $check['allowed']) {
            throw ValidationException::withMessages([
                'exam' => $check['reason'],
            ]);
        }

        if (isset($check['active_attempt'])) {
            return $check['active_attempt'];
        }

        $enrollment = $check['enrollment'];
        $durationMinutes = (int) ($exam->duration_minutes ?: 60);

        $attempt = ExamAttempt::create([
            'uuid' => (string) Str::uuid(),
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'enrollment_id' => $enrollment->id,
            'attempt_number' => $check['next_attempt_number'],
            'status' => 'in_progress',
            'started_at' => now(),
            'expires_at' => now()->addMinutes($durationMinutes),
        ]);

        activity()
            ->performedOn($attempt)
            ->causedBy($user)
            ->withProperties(['attempt_number' => $attempt->attempt_number])
            ->log('Exam attempt started');

        return $attempt;
    }

    /**
     * Auto-save answers with debounce while typing.
     * Enforces timer: automatically submits if time has expired.
     */
    public function saveAnswers(ExamAttempt $attempt, array $answers): array
    {
        if ($attempt->status !== 'in_progress') {
            return [
                'status' => 'already_submitted',
                'message' => 'This exam attempt has already been submitted.',
                'attempt' => $attempt->load(['answers.question']),
            ];
        }

        // Server timer check
        $effectiveExpiresAt = $attempt->upload_grace_period_expires_at ?? $attempt->expires_at;
        if (now()->greaterThan($effectiveExpiresAt)) {
            $submittedAttempt = $this->submitAttempt($attempt);
            return [
                'status' => 'expired_and_submitted',
                'message' => 'Time has expired. Your examination was automatically submitted.',
                'attempt' => $submittedAttempt,
            ];
        }

        foreach ($answers as $item) {
            $question = ExamQuestion::where('exam_id', $attempt->exam_id)
                ->where('uuid', $item['question_uuid'] ?? '')
                ->first();

            if (! $question) {
                continue;
            }

            ExamAttemptAnswer::updateOrCreate(
                [
                    'exam_attempt_id' => $attempt->id,
                    'exam_question_id' => $question->id,
                ],
                [
                    'selected_option' => $item['selected_option'] ?? null,
                    'text_answer' => $item['text_answer'] ?? null,
                    'media_path' => $item['media_path'] ?? null,
                    'media_mime' => $item['media_mime'] ?? null,
                ]
            );
        }

        return [
            'status' => 'saved',
            'saved_at' => now()->toIso8601String(),
            'attempt' => $attempt->fresh(['answers']),
        ];
    }

    /**
     * Stop exam timer once performance recording is captured,
     * and grant a 15-minute upload grace period.
     */
    public function recordPerformanceCaptured(ExamAttempt $attempt, string $questionUuid, ?string $mediaPath = null, ?string $mime = null): ExamAttempt
    {
        if ($attempt->status !== 'in_progress') {
            return $attempt;
        }

        $question = ExamQuestion::where('exam_id', $attempt->exam_id)
            ->where('uuid', $questionUuid)
            ->firstOrFail();

        $stoppedAt = now();
        $graceExpiresAt = $stoppedAt->copy()->addMinutes(15);

        $attempt->update([
            'recording_stopped_at' => $stoppedAt,
            'upload_grace_period_expires_at' => $graceExpiresAt,
        ]);

        if ($mediaPath) {
            ExamAttemptAnswer::updateOrCreate(
                [
                    'exam_attempt_id' => $attempt->id,
                    'exam_question_id' => $question->id,
                ],
                [
                    'media_path' => $mediaPath,
                    'media_mime' => $mime,
                    'media_disk' => 'local',
                ]
            );
        }

        activity()
            ->performedOn($attempt)
            ->causedBy($attempt->user)
            ->withProperties(['question_uuid' => $questionUuid, 'grace_expires_at' => $graceExpiresAt])
            ->log('Recorded performance captured. 15-minute upload grace period initiated.');

        return $attempt->fresh(['answers']);
    }

    /**
     * Submit an exam attempt.
     * Auto-grades multiple choice questions.
     * If manual grading required, status transitions to 'grading'.
     * If 100% auto-graded, computes score, pass/fail, and issues certificate if eligible.
     */
    public function submitAttempt(ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->status !== 'in_progress') {
            return $attempt;
        }

        $attempt->load(['exam.questions', 'answers', 'enrollment']);
        $questions = $attempt->exam->questions;

        // Auto-grade multiple choice questions
        foreach ($questions as $q) {
            if ($q->question_type === 'multiple_choice') {
                $answer = $attempt->answers->firstWhere('exam_question_id', $q->id);
                $isCorrect = $answer && trim((string) $answer->selected_option) === trim((string) $q->correct_answer);
                $scoreAwarded = $isCorrect ? (float) $q->points : 0.00;

                ExamAttemptAnswer::updateOrCreate(
                    [
                        'exam_attempt_id' => $attempt->id,
                        'exam_question_id' => $q->id,
                    ],
                    [
                        'is_auto_graded' => true,
                        'score_awarded' => $scoreAwarded,
                        'graded_at' => now(),
                    ]
                );
            }
        }

        // Check if any question requires manual grading (theory or performance)
        $hasManualQuestions = $questions->contains(function ($q) {
            return in_array($q->question_type, ['theory', 'recorded_performance']);
        });

        if ($hasManualQuestions) {
            $attempt->update([
                'status' => 'grading',
                'submitted_at' => now(),
            ]);
        } else {
            // Auto-grade completely
            $attempt->refresh();
            $totalScore = (float) $attempt->answers()->sum('score_awarded');
            $maxScore = (float) $questions->sum('points');
            $scorePercent = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0.00;
            $passMark = (int) ($attempt->enrollment->exam_pass_mark_percent ?: 70);
            $passed = $scorePercent >= $passMark;

            $attempt->update([
                'status' => 'graded',
                'submitted_at' => now(),
                'total_score' => $totalScore,
                'max_score' => $maxScore,
                'score_percent' => $scorePercent,
                'passed' => $passed,
                'graded_at' => now(),
            ]);

            if ($passed) {
                $this->certificateService->issueIfEligible($attempt->enrollment, $attempt);
            }
        }

        activity()
            ->performedOn($attempt)
            ->causedBy($attempt->user)
            ->log('Exam attempt submitted');

        return $attempt->fresh(['answers.question']);
    }
}
