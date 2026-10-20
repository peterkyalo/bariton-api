<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamRubricScore;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ExamGradingService
{
    public function __construct(
        protected CertificateService $certificateService
    ) {}

    /**
     * Submit manual grades for theory and performance questions in an exam attempt.
     */
    public function gradeAttempt(ExamAttempt $attempt, User $instructor, array $evaluations, ?string $overallFeedback = null): ExamAttempt
    {
        if (! in_array($attempt->status, ['submitted', 'grading', 'graded'])) {
            throw ValidationException::withMessages([
                'status' => 'This exam attempt is still in progress and cannot be graded.',
            ]);
        }

        $attempt->load(['exam.questions', 'answers.question.rubric.criteria', 'enrollment']);

        foreach ($evaluations as $eval) {
            $answer = $attempt->answers->firstWhere('uuid', $eval['answer_uuid'] ?? '');
            if (! $answer) {
                continue;
            }

            $question = $answer->question;
            $totalPointsFromRubric = 0.00;

            // Handle rubric scoring if criteria scores provided
            if (! empty($eval['rubric_scores']) && is_array($eval['rubric_scores'])) {
                foreach ($eval['rubric_scores'] as $rScore) {
                    $criterionId = $rScore['rubric_criterion_id'] ?? null;
                    $points = (float) ($rScore['points'] ?? 0.00);
                    $totalPointsFromRubric += $points;

                    ExamRubricScore::updateOrCreate(
                        [
                            'exam_attempt_answer_id' => $answer->id,
                            'rubric_criterion_id' => $criterionId,
                        ],
                        [
                            'rubric_level_id' => $rScore['rubric_level_id'] ?? null,
                            'points_awarded' => $points,
                            'comments' => $rScore['comments'] ?? null,
                        ]
                    );
                }
            }

            // If explicit score_awarded is passed, prioritize it; otherwise use rubric sum
            $scoreAwarded = isset($eval['score_awarded'])
                ? (float) $eval['score_awarded']
                : $totalPointsFromRubric;

            // Cap at question points
            $scoreAwarded = min($scoreAwarded, (float) $question->points);

            $answer->update([
                'score_awarded' => $scoreAwarded,
                'feedback' => $eval['feedback'] ?? null,
                'graded_by' => $instructor->id,
                'graded_at' => now(),
            ]);
        }

        // Recompute attempt aggregates
        $attempt->refresh();
        $totalScore = (float) $attempt->answers()->sum('score_awarded');
        $maxScore = (float) $attempt->exam->questions()->sum('points');
        $scorePercent = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0.00;
        $passMark = (int) ($attempt->enrollment->exam_pass_mark_percent ?: 70);
        $passed = $scorePercent >= $passMark;

        $attempt->update([
            'status' => 'graded',
            'total_score' => $totalScore,
            'max_score' => $maxScore,
            'score_percent' => $scorePercent,
            'passed' => $passed,
            'overall_feedback' => $overallFeedback,
            'graded_by' => $instructor->id,
            'graded_at' => now(),
        ]);

        if ($passed) {
            $this->certificateService->issueIfEligible($attempt->enrollment, $attempt);
        }

        activity()
            ->performedOn($attempt)
            ->causedBy($instructor)
            ->withProperties([
                'total_score' => $totalScore,
                'max_score' => $maxScore,
                'score_percent' => $scorePercent,
                'passed' => $passed,
            ])
            ->log('Exam attempt graded by instructor');

        return $attempt->fresh([
            'answers.question.rubric.criteria.levels',
            'answers.rubricScores',
            'gradedByUser',
            'certificate',
        ]);
    }
}
