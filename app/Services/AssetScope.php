<?php

namespace App\Services;

use App\Enums\MachineType;
use App\Models\Machine;
use App\Models\User;

/**
 * Aturan "ruang lingkup" aset: menyatukan cara menentukan apakah seorang user
 * boleh melihat unit bertipe mesin atau utility. Dipakai bersama oleh form PM
 * dan riwayat unit supaya batasan tech-machines / tech-utilities konsisten di
 * semua endpoint (tabel `assets` menampung mesin dan utility sekaligus).
 *
 * Scope dianggap terbuka bila user punya permission khusus tipenya
 * (machine.view/machine.manage untuk mesin; utility.view untuk utility) atau
 * baseline `dashboard.view` yang dipakai role general.
 */
class AssetScope
{
    public static function allows(?User $user, Machine $machine): bool
    {
        if ($user === null) {
            return false;
        }

        $machineScope = $user->can('machine.manage')
            || $user->can('machine.view')
            || $user->can('dashboard.view');

        $utilityScope = $user->can('utility.view')
            || $user->can('dashboard.view');

        return $machine->type === MachineType::Utility->value
            ? $utilityScope
            : $machineScope;
    }
}
