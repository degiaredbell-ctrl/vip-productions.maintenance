<?php

namespace App\Services\Notifications;

use App\Enums\MachineType;
use App\Enums\Period;
use App\Enums\PmDisplayStatus;
use App\Services\DashboardService;
use App\Services\PeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Daftar perangkat untuk notifikasi terjadwal. Memakai DashboardService yang
 * sama dengan Beranda / Form Maintenance, jadi perangkat yang disebut
 * "terlambat" di notifikasi selalu sama dengan yang ditandai merah di layar.
 */
class PmScheduleScanner
{
    /**
     * Konteks periode berjalan (nama periode selalu dinamis).
     *
     * @return array{period: Period, year: int, label: string, week: ?int, start: string, end: string}
     */
    public static function context(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $period = Period::fromMonth((int) $now->month);
        $year = (int) $now->year;
        $start = PeriodService::periodStart($period, $year);

        return [
            'period' => $period,
            'year' => $year,
            'label' => self::periodLabel($period, $year),
            'week' => PeriodService::currentWeek($period, $year),
            'start' => $start->format('d/m/Y'),
            'end' => $start->addMonths(2)->subDay()->format('d/m/Y'),
        ];
    }

    /** "Sep–Okt 2026" */
    public static function periodLabel(Period $period, int $year): string
    {
        return $period->label().' '.$year;
    }

    /**
     * Semua perangkat aktif (mesin + utility) pada periode, dengan status PM.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function devices(Period $period, int $year): Collection
    {
        $today = CarbonImmutable::now()->startOfDay();

        return DashboardService::machineStatuses($year, $period)
            ->concat(DashboardService::machineStatuses($year, $period, MachineType::Utility))
            ->map(function (array $m) use ($period, $year, $today) {
                $deadline = PeriodService::weekDeadline($period, $year, (int) $m['week_group']);
                $m['scope'] = NotificationRecipients::scopeOf($m['type']);
                $m['deadline_label'] = $deadline->subDay()->format('d/m/Y');
                $m['days_late'] = $m['overdue'] ? (int) $deadline->startOfDay()->diffInDays($today) + 1 : 0;

                return $m;
            })
            ->values();
    }

    /**
     * Perangkat yang masih harus di-PM pada periode (Belum / Perlu Revisi),
     * urut minggu jadwal.
     */
    public static function pending(Period $period, int $year): Collection
    {
        return self::devices($period, $year)
            ->filter(fn ($m) => in_array($m['status'], [PmDisplayStatus::Todo->value, PmDisplayStatus::Issue->value], true))
            ->sortBy([['week_group', 'asc'], ['code', 'asc']])
            ->values();
    }

    /** Perangkat terlambat: minggu jadwal sudah lewat dan PM belum selesai dikerjakan. */
    public static function overdue(Period $period, int $year): Collection
    {
        return self::devices($period, $year)
            ->filter(fn ($m) => $m['overdue'])
            ->sortBy([['days_late', 'desc'], ['code', 'asc']])
            ->values();
    }

    /**
     * Bentuk ringkas untuk payload notifikasi / email.
     *
     * @return array<string, mixed>
     */
    public static function item(array $m, Period $period, int $year): array
    {
        return [
            'id' => $m['id'],
            'code' => $m['code'],
            'name' => $m['name'],
            'location' => $m['location'],
            'type_label' => $m['type_label'],
            'scope' => $m['scope'],
            'week' => (int) $m['week_group'],
            'deadline' => $m['deadline_label'],
            'status' => $m['status_label'],
            'days_late' => $m['days_late'],
            'path' => route('machines.pm.create', ['machine' => $m['id'], 'year' => $year, 'period' => $period->value], false),
        ];
    }
}
