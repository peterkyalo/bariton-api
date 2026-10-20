<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Module;
use App\Models\User;

class ModulePolicy
{
    public function view(?User $user, Module $module): bool
    {
        return true;
    }

    public function create(User $user, Course $course): bool
    {
        return $user->can('courses.manage-all')
            || ($user->can('courses.teach') && $course->instructors->contains($user->id));
    }

    public function update(User $user, Module $module): bool
    {
        return $user->can('courses.manage-all')
            || ($user->can('courses.teach') && $module->course->instructors->contains($user->id));
    }

    public function delete(User $user, Module $module): bool
    {
        return $user->can('courses.delete')
            || ($user->can('courses.teach') && $module->course->instructors->contains($user->id));
    }
}
