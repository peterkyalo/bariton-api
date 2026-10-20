<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

class LessonPolicy
{
    public function view(?User $user, Lesson $lesson): bool
    {
        if ($lesson->is_preview) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->can('courses.manage-all')) {
            return true;
        }

        $course = $lesson->module->course;
        if ($user->can('courses.teach') && $course->instructors->contains($user->id)) {
            return true;
        }

        // Student enrollment check
        $enrollment = $course->enrollments()->where('user_id', $user->id)->first();
        if (! $enrollment) {
            return false;
        }

        if ($enrollment->path_type === 'validation') {
            return false;
        }

        return $enrollment->enrollmentModules()->where('module_id', $lesson->module_id)->exists();
    }

    public function create(User $user, Module $module): bool
    {
        return $user->can('courses.manage-all')
            || ($user->can('courses.teach') && $module->course->instructors->contains($user->id));
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->can('courses.manage-all')
            || ($user->can('courses.teach') && $lesson->module->course->instructors->contains($user->id));
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->can('courses.delete')
            || ($user->can('courses.teach') && $lesson->module->course->instructors->contains($user->id));
    }
}
