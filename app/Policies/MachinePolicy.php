<?php

namespace App\Policies;

use App\Models\Machine;
use App\Models\User;

class MachinePolicy
{
    public function view(User $user): bool
    {
        return $user->can('dashboard.view');
    }

    public function create(User $user): bool
    {
        return $user->can('machine.manage');
    }

    public function update(User $user, Machine $machine): bool
    {
        return $user->can('machine.manage');
    }

    public function delete(User $user, Machine $machine): bool
    {
        return $user->can('machine.manage');
    }
}
