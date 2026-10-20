<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Course $course): bool
    {
        if ($course->status === 'published') {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $user->can('courses.manage-all')
            || ($user->can('courses.teach') && $course->instructors->contains($user->id));
    }

    public function create(User $user): bool
    {
        return $user->can('courses.create');
    }

    public function update(User $user, Course $course): bool
    {
        return $user->can('courses.manage-all')
            || ($user->can('courses.teach') && $course->instructors->contains($user->id));
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->can('courses.delete');
    }
}
