<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $user): bool
    {
        return $user->can('user.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('user.manage');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('user.manage');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('user.manage') && $user->id !== $model->id;
    }
}
