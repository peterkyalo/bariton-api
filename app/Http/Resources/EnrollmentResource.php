<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unlockedModuleIds = $this->enrollmentModules->pluck('module_id')->toArray();

        $progressMap = [];
        foreach ($this->lessonProgress as $progress) {
            $progressMap[$progress->lesson_id] = [
                'status' => $progress->status,
                'progress_percent' => $progress->progress_percent,
                'last_position_seconds' => $progress->last_position_seconds,
                'completed_at' => $progress->completed_at?->toISOString(),
            ];
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'path_type' => $this->path_type,
            'status' => $this->status,
            'progress_percent' => (float) $this->progress_percent,
            'completed_at' => $this->completed_at?->toISOString(),
            'course' => [
                'id' => $this->course->id,
                'uuid' => $this->course->uuid,
                'title' => $this->course->title,
                'slug' => $this->course->slug,
                'short_description' => $this->course->short_description,
                'requires_sequential_levels' => (bool) $this->course->requires_sequential_levels,
            ],
            'unlocked_module_ids' => $unlockedModuleIds,
            'modules' => $this->course->modules->map(function ($module) use ($unlockedModuleIds, $progressMap) {
                $isUnlocked = in_array($module->id, $unlockedModuleIds);

                $lessons = $module->lessons->map(function ($lesson) use ($progressMap, $isUnlocked) {
                    $prog = $progressMap[$lesson->id] ?? null;

                    return [
                        'id' => $lesson->id,
                        'uuid' => $lesson->uuid,
                        'title' => $lesson->title,
                        'slug' => $lesson->slug,
                        'summary' => $lesson->summary,
                        'order' => $lesson->order,
                        'type' => $lesson->type,
                        'duration_seconds' => $lesson->duration_seconds,
                        'is_preview' => (bool) $lesson->is_preview,
                        'is_accessible' => $isUnlocked || (bool) $lesson->is_preview,
                        'progress' => $prog,
                    ];
                });

                $totalLessons = $lessons->count();
                $completedLessons = $lessons->filter(fn ($l) => ($l['progress']['status'] ?? '') === 'completed')->count();
                $moduleProgress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

                return [
                    'id' => $module->id,
                    'uuid' => $module->uuid,
                    'title' => $module->title,
                    'slug' => $module->slug,
                    'level' => $module->level,
                    'order' => $module->order,
                    'is_unlocked' => $isUnlocked,
                    'progress_percent' => $moduleProgress,
                    'lessons' => $lessons,
                ];
            }),
            'progress_map' => $progressMap,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
