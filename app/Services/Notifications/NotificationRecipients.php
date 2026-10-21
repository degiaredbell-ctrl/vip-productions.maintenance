<?php

namespace App\Services\Notifications;

use App\Enums\MachineType;
use App\Enums\Role;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Models\User;
use App\Services\SignatureChain;
use Illuminate\Support\Collection;

/**
 * Aturan penerima notifikasi, satu sumber untuk semua saluran:
 *
 * - Teknisi : pemegang `pm.sign` (bukan Admin). Scope mengikuti jenis
 *             perangkat — `machine.view`/`machine.manage` untuk mesin
 *             (tech-machines), `utility.view` untuk utility (tech-utilities).
 *             Role teknisi umum (`dashboard.view`) dan tech-all mendapat keduanya.
 * - User/PIC: hanya perangkat yang menjadi tanggung jawabnya, yaitu PIC yang
 *             ditetapkan di perangkat; bila kosong, PIC pada PM terakhir
 *             perangkat itu.
 * - Atasan  : pemegang `pm.approve` selain Admin; Admin hanya dipakai bila
 *             tidak ada orang lain (sama dengan aturan email approval lama).
 */
class NotificationRecipients
{
    public const SCOPE_MACHINE = 'machine';
    public const SCOPE_UTILITY = 'utility';

    /** Scope perangkat untuk satu tipe aset. */
    public static function scopeOf(?string $type): string
    {
        return $type === MachineType::Utility->value ? self::SCOPE_UTILITY : self::SCOPE_MACHINE;
    }

    /**
     * Teknisi beserta scope perangkatnya.
     *
     * @return Collection<int, array{user: User, scopes: list<string>}>
     */
    public static function technicians(): Collection
    {
        return User::permission('pm.sign')
            ->with('roles', 'permissions')
            ->orderBy('name')
            ->get()
            ->reject(fn (User $u) => $u->hasRole(Role::Admin->value))
            ->map(function (User $u) {
                $scopes = [];
                if ($u->can('machine.view') || $u->can('machine.manage') || $u->can('dashboard.view')) {
                    $scopes[] = self::SCOPE_MACHINE;
                }
                if ($u->can('utility.view') || $u->can('utility.manage') || $u->can('dashboard.view')) {
                    $scopes[] = self::SCOPE_UTILITY;
                }

                return ['user' => $u, 'scopes' => $scopes];
            })
            ->filter(fn ($row) => $row['scopes'] !== [])
            ->values();
    }

    /** @return Collection<int, User> */
    public static function supervisors(): Collection
    {
        $holders = User::permission('pm.approve')->with('roles')->orderBy('name')->get();
        $users = $holders->reject(fn (User $u) => $u->hasRole(Role::Admin->value));

        return ($users->isEmpty() ? $holders : $users)->values();
    }

    /**
     * PIC efektif per perangkat: [machine_id => user_id].
     *
     * @param  iterable<int>  $machineIds
     * @return array<int, int>
     */
    public static function picMap(iterable $machineIds): array
    {
        $ids = collect($machineIds)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $assigned = Machine::withTrashed()->whereIn('id', $ids)->whereNotNull('pic_user_id')
            ->pluck('pic_user_id', 'id')->map(fn ($v) => (int) $v)->all();

        $missing = $ids->reject(fn ($id) => isset($assigned[$id]))->values();
        $fallback = self::lastPicFromHistory($missing);

        return $assigned + $fallback;
    }

    /**
     * PIC dari PM terakhir yang punya penugasan PIC, untuk perangkat yang
     * belum ditetapkan PIC-nya.
     *
     * @param  Collection<int, int>  $machineIds
     * @return array<int, int>
     */
    public static function lastPicFromHistory(Collection $machineIds): array
    {
        if ($machineIds->isEmpty()) {
            return [];
        }

        $map = [];
        PmRecord::query()
            ->whereIn('machine_id', $machineIds)
            ->whereNotNull('pic_user_id')
            ->orderByDesc('year')
            ->orderByDesc('updated_at')
            ->get(['machine_id', 'pic_user_id'])
            ->each(function ($r) use (&$map) {
                $map[$r->machine_id] ??= (int) $r->pic_user_id;
            });

        return $map;
    }

    /**
     * Penerima 3.1 (teknisi selesai Create PM): User PIC yang ditunjuk pada
     * dokumen; dokumen lama tanpa PIC → PIC perangkat → semua User PIC.
     *
     * @return Collection<int, User>
     */
    public static function picForRecord(PmRecord $record): Collection
    {
        if ($record->picUser) {
            return collect([$record->picUser]);
        }

        $machinePic = $record->machine?->pic_user_id ? User::find($record->machine->pic_user_id) : null;

        return $machinePic ? collect([$machinePic]) : SignatureChain::picCandidates()->get();
    }
}
