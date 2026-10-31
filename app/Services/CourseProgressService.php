<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;

class CourseProgressService
{
    /**
     * Record lesson progress and auto-complete when reaching completion criteria.
     * YouTube/Audio: >= 90% watched/played.
     * PDF/Richtext: explicit completion trigger or 100%.
     */
    public function recordProgress(
        Enrollment $enrollment,
        Lesson $lesson,
        int $progressPercent,
        int $positionSeconds = 0,
        bool $markComplete = false
    ): LessonProgress {
        $progress = LessonProgress::firstOrNew([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
        ]);

        $progress->last_position_seconds = $positionSeconds;
        $progress->progress_percent = max($progress->progress_percent ?? 0, min(100, $progressPercent));
        $progress->last_watched_at = now();

        $shouldComplete = $markComplete
            || $progress->progress_percent >= 90
            || (in_array($lesson->type, ['pdf', 'richtext', 'text']) && $markComplete);

        if ($shouldComplete && $progress->status !== 'completed') {
            $progress->status = 'completed';
            $progress->progress_percent = 100;
            $progress->completed_at = now();
        } elseif (! $progress->exists) {
            $progress->status = 'in_progress';
        }

        $progress->save();

        // Recalculate overall enrollment progress
        $this->recalculateEnrollmentProgress($enrollment);

        return $progress;
    }

    /**
     * Recalculate module and overall course progress percentages for an enrollment.
     */
    public function recalculateEnrollmentProgress(Enrollment $enrollment): void
    {
        $enrollment->loadMissing(['course.modules.lessons', 'lessonProgress', 'enrollmentModules']);

        // Check each enrolled module for completion
        foreach ($enrollment->enrollmentModules as $enrollmentModule) {
            $module = $enrollmentModule->module;
            if (! $module) {
                continue;
            }

            $moduleLessonIds = $module->lessons->pluck('id');
            $moduleTotalLessons = $moduleLessonIds->count();

            if ($moduleTotalLessons > 0) {
                $completedCount = $enrollment->lessonProgress
                    ->whereIn('lesson_id', $moduleLessonIds)
                    ->where('status', 'completed')
                    ->count();

                if ($completedCount === $moduleTotalLessons && is_null($enrollmentModule->completed_at)) {
                    $enrollmentModule->update(['completed_at' => now()]);
                }
            }
        }

        // Calculate course progress based on unlocked/enrolled modules
        $unlockedModuleIds = $enrollment->enrollmentModules->pluck('module_id');
        $enrolledLessons = $enrollment->course->modules
            ->whereIn('id', $unlockedModuleIds)
            ->flatMap->lessons;

        $totalLessons = $enrolledLessons->count();

        if ($totalLessons === 0) {
            $enrollment->update(['progress_percent' => 0.00]);

            return;
        }

        $completedLessonsCount = $enrollment->lessonProgress
            ->whereIn('lesson_id', $enrolledLessons->pluck('id'))
            ->where('status', 'completed')
            ->count();

        $percent = round(($completedLessonsCount / $totalLessons) * 100, 2);

        $updates = ['progress_percent' => $percent];

        if ($percent >= 100.00 && is_null($enrollment->completed_at)) {
            $updates['status'] = 'completed';
            $updates['completed_at'] = now();
        }

        $enrollment->update($updates);
    }
}
