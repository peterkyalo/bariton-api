<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->can('users.update');
    }

    public function assignRoles(User $user, User $model): bool
    {
        return $user->can('users.assign-roles');
    }

    public function updateStatus(User $user, User $model): bool
    {
        return $user->can('users.update');
    }
}
