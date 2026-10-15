<?php

namespace App\Policies;

use App\Models\PmRecord;
use App\Models\User;
use App\Services\SignatureChain;

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

        // Checklist yang sudah ditandatangani tidak boleh diubah; kalau boleh,
        // approval di hilir bisa jadi tidak sesuai dengan isi yang direview.
        if (!$record->status->isEditable()) return false;

        if ($user->hasRole('technician') && $record->technician_id !== $user->id) return false;

        return true;
    }

    /**
     * Hard delete permanen sebuah PM Record (halaman admin). Hanya pemegang
     * `pm.manage` yang boleh, sejalan dengan middleware `can:pm.manage` pada
     * route /admin/pm-records.
     */
    public function delete(User $user, PmRecord $record): bool
    {
        return $user->can('pm.manage');
    }

    /**
     * Satu sumber aturan untuk "apakah user ini boleh menandatangani / menolak
     * record ini", dipakai juga oleh halaman Persetujuan supaya badge antrean
     * dan daftar yang diklik tidak bisa berbeda dengan yang diizinkan server.
     */
    public function sign(User $user, PmRecord $record): bool
    {
        return SignatureChain::canSign($user, $record);
    }

    public function reject(User $user, PmRecord $record): bool
    {
        return SignatureChain::canReject($user, $record);
    }
}
