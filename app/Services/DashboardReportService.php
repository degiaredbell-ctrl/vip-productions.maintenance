<?php

namespace App\Services;

use App\Enums\MachineType;
use App\Enums\Period;
use App\Models\Machine;

/**
 * Rekap dashboard per tipe (machine|utility) untuk satu tahun.
 *
 * Angka di sini WAJIB sama dengan yang dipakai Beranda/ekspor, jadi
 * perhitungan status memakai DashboardService::machineStatuses() yang sama
 * (pemetaan PmStatus -> PmDisplayStatus ada di PmDisplayStatus). Dengan begitu
 * chart dashboard tidak pernah menampilkan "Selesai" berbeda dari daftar PM.
 */
class DashboardReportService
{
    /**
     * @return array{
     *  byPeriod: array<int,array{period:string,label:string,total:int,done:int,progress:int,todo:int,issue:int,percent:int,locked:bool,isCurrent:bool}>,
     *  byStatus: array{done:int,progress:int,todo:int,issue:int,total:int,percent:int},
     *  totalUnits:int
     * }
     */
    public static function build(int $year, string $type): array
    {
        $machineType = $type === 'utility' ? MachineType::Utility : null;

        $byPeriod = [];
        $byStatus = ['done' => 0, 'progress' => 0, 'todo' => 0, 'issue' => 0, 'total' => 0, 'percent' => 0];

        foreach (Period::cases() as $period) {
            $locked = $period->isFuture($year);

            // Periode yang belum tiba belum punya pekerjaan, jadi tidak
            // dihitung sebagai "Belum" (todo) pada rekap tahunan.
            $stats = $locked
                ? ['total' => 0, 'done' => 0, 'progress' => 0, 'todo' => 0, 'issue' => 0]
                : DashboardService::stats(DashboardService::machineStatuses($year, $period, $machineType));

            $byPeriod[] = [
                'period' => $period->value,
                'label' => $period->label(),
                'total' => $stats['total'],
                'done' => $stats['done'],
                'progress' => $stats['progress'],
                'todo' => $stats['todo'],
                'issue' => $stats['issue'],
                'percent' => $stats['total'] > 0 ? (int) round(($stats['done'] / $stats['total']) * 100) : 0,
                'locked' => $locked,
                'isCurrent' => $period === Period::current() && $year === (int) now()->year,
            ];

            if (! $locked) {
                foreach (['done', 'progress', 'todo', 'issue'] as $key) {
                    $byStatus[$key] += $stats[$key];
                }
                $byStatus['total'] += $stats['total'];
            }
        }

        $byStatus['percent'] = $byStatus['total'] > 0
            ? (int) round(($byStatus['done'] / $byStatus['total']) * 100)
            : 0;

        $totalUnits = Machine::where('is_active', true)
            ->when(
                $machineType === null,
                fn ($q) => $q->where('type', '!=', MachineType::Utility->value),
                fn ($q) => $q->where('type', $machineType->value)
            )
            ->count();

        return [
            'byPeriod' => $byPeriod,
            'byStatus' => $byStatus,
            'totalUnits' => $totalUnits,
        ];
    }
}
