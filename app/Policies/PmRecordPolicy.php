<?php

namespace App\Policies;

use App\Models\PmRecord;
use App\Models\User;

class PmRecordPolicy
{
    public function view(User $user): bool
    {
        return $user->can('pm.history.view');
    }

    public function create(User $user): bool
    {
        return $user->can('pm.fill');
    }

    public function update(User $user, PmRecord $record): bool
    {
        if (!$user->can('pm.fill')) return false;
        if ($record->status->value === 'approved') return false;
        if ($user->hasRole('technician') && $record->technician_id !== $user->id) return false;
        return true;
    }

    public function approve(User $user): bool
    {
        return $user->can('pm.approve');
    }
}
