<?php

namespace App\Services\Grading;

use App\Models\AssignmentSubmission;
use App\Models\SubmissionRubricScore;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignmentGradingService
{
    /**
     * Grade an assignment submission using the associated rubric template.
     */
    public function grade(AssignmentSubmission $submission, User $instructor, array $data): AssignmentSubmission
    {
        $rubric = $submission->assignment->rubric;
        $scoresData = $data['rubric_scores'] ?? []; // array of [ 'criterion_id' => x, 'level_id' => y, 'comments' => ... ]
        $generalFeedback = $data['feedback'] ?? '';

        return DB::transaction(function () use ($submission, $instructor, $rubric, $scoresData, $generalFeedback, $data) {
            $totalEarned = 0;
            $totalPossible = 0;

            // Delete previous scores if regrading
            $submission->rubricScores()->delete();

            if ($rubric) {
                $criteria = $rubric->criteria()->with('levels')->get();
                $criteriaMap = $criteria->keyBy('id');

                foreach ($scoresData as $item) {
                    $criterionId = $item['criterion_id'] ?? null;
                    $levelId = $item['level_id'] ?? null;
                    $comments = $item['comments'] ?? null;

                    $criterion = $criteriaMap->get($criterionId);
                    if (! $criterion) {
                        continue;
                    }

                    $level = $criterion->levels->firstWhere('id', $levelId);
                    $points = $level ? $level->points : ($item['points'] ?? 0);

                    SubmissionRubricScore::create([
                        'assignment_submission_id' => $submission->id,
                        'rubric_criterion_id' => $criterion->id,
                        'rubric_level_id' => $levelId,
                        'points_awarded' => $points,
                        'criterion_feedback' => $comments,
                    ]);

                    $totalEarned += $points;
                    $totalPossible += $criterion->max_points;
                }

                // If some criteria were not explicitly scored, include their max in total possible
                foreach ($criteria as $crit) {
                    if (! collect($scoresData)->contains('criterion_id', $crit->id)) {
                        $totalPossible += $crit->max_points;
                    }
                }
            } else {
                // Direct score fallback
                $totalEarned = (int) ($data['total_points_earned'] ?? 0);
                $totalPossible = (int) ($data['total_points_possible'] ?? 100);
            }

            if ($totalPossible <= 0) {
                $totalPossible = 100;
            }

            $scorePercent = round(($totalEarned / $totalPossible) * 100, 2);

            // Pass mark stored on enrollment
            $enrollment = $submission->enrollment;
            $passMark = $enrollment->assignment_pass_mark_percent ?? 70;

            $passed = $scorePercent >= $passMark;
            $status = $passed ? 'graded' : 'resubmission_required';

            $submission->update([
                'status' => $status,
                'score_percent' => $scorePercent,
                'total_points_earned' => $totalEarned,
                'total_points_possible' => $totalPossible,
                'passed' => $passed,
                'feedback' => $generalFeedback,
                'graded_by' => $instructor->id,
                'graded_at' => now(),
            ]);

            activity('assessment')
                ->performedOn($submission)
                ->causedBy($instructor)
                ->withProperties([
                    'assignment' => $submission->assignment->title,
                    'student' => $submission->student->name,
                    'score_percent' => $scorePercent,
                    'passed' => $passed,
                    'status' => $status,
                ])
                ->log("Assignment submission #{$submission->id} graded with score {$scorePercent}% ({$status}).");

            return $submission->load(['rubricScores.criterion', 'rubricScores.level', 'grader']);
        });
    }
}
