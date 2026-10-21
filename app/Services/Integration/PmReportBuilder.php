<?php

namespace App\Services\Integration;

use App\Enums\MachineType;
use App\Enums\Period;
use App\Enums\PmStatus;
use App\Models\AuditLog;
use App\Models\PmRecord;
use App\Models\SparePart;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;

/**
 * Isi laporan PM otomatis (mingguan / bulanan) yang dikirim lewat email.
 *
 * Angka status memakai DashboardService yang sama dengan Beranda supaya
 * laporan email tidak pernah berbeda dari layar.
 */
class PmReportBuilder
{
    public const WEEKLY = 'weekly';
    public const MONTHLY = 'monthly';

    /**
     * @return array<string, mixed>
     */
    public static function build(string $type, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        // Mingguan = 7 hari terakhir; bulanan = bulan kalender sebelumnya.
        [$from, $to, $rangeLabel] = $type === self::MONTHLY
            ? [
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
                $now->subMonthNoOverflow()->locale('id')->translatedFormat('F Y'),
            ]
            : [
                $now->subDays(6)->startOfDay(),
                $now->endOfDay(),
                $now->subDays(6)->format('d/m/Y').' – '.$now->format('d/m/Y'),
            ];

        $period = Period::current();
        $year = (int) $now->year;

        $status = [];
        foreach (['machine' => null, 'utility' => MachineType::Utility] as $key => $machineType) {
            $stats = DashboardService::stats(DashboardService::machineStatuses($year, $period, $machineType));
            $stats['percent'] = $stats['total'] > 0 ? (int) round($stats['done'] / $stats['total'] * 100) : 0;
            $status[$key] = $stats;
        }

        $activity = [
            'submitted' => PmRecord::whereBetween('submitted_at', [$from, $to])->count(),
            'approved' => PmRecord::whereBetween('approved_at', [$from, $to])->count(),
            'rejected' => AuditLog::where('action', 'pm.reject')->whereBetween('created_at', [$from, $to])->count(),
        ];

        $pending = [
            'pic' => PmRecord::where('status', PmStatus::Submitted->value)->count(),
            'supervisor' => PmRecord::where('status', PmStatus::PicApproved->value)->count(),
            'revision' => PmRecord::where('status', PmStatus::Rejected->value)->count(),
        ];

        $lowParts = SparePart::query()
            ->where(fn ($q) => $q->where('stock', '<=', 0)->orWhere(fn ($q) => $q->where('min_stock', '>', 0)->whereColumn('stock', '<=', 'min_stock')))
            ->orderBy('stock')
            ->orderBy('name')
            ->get(['code', 'name', 'stock', 'min_stock', 'unit']);

        return [
            'type' => $type,
            'title' => $type === self::MONTHLY ? 'Laporan PM Bulanan' : 'Laporan PM Mingguan',
            'rangeLabel' => $rangeLabel,
            'periodLabel' => $period->label().' '.$year,
            'period' => $period->value,
            'year' => $year,
            'status' => $status,
            'activity' => $activity,
            'pending' => $pending,
            'stock' => [
                'out' => $lowParts->where('stock', '<=', 0)->count(),
                'low' => $lowParts->where('stock', '>', 0)->count(),
                'items' => $lowParts->take(10)->values()->all(),
            ],
            'generatedAt' => $now->format('d/m/Y H:i'),
        ];
    }
}
