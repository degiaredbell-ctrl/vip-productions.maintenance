<?php

namespace App\Services;

use App\Enums\MachineType;
use App\Enums\Period;
use App\Enums\PmStatus;
use App\Models\Machine;
use App\Models\PmRecord;
use Carbon\CarbonImmutable;

class PeriodService
{
    public static function currentPeriod(): Period
    {
        return Period::current();
    }

    public static function currentYear(): int
    {
        return (int) now()->year;
    }

    public static function isFuturePeriod(Period $period, int $year): bool
    {
        return $period->isFuture($year);
    }

    public static function periodStart(Period $period, int $year): CarbonImmutable
    {
        return CarbonImmutable::create($year, $period->startMonth(), 1)->startOfDay();
    }

    /**
     * Minggu ke-N dihitung per 7 hari sejak tanggal 1 awal periode
     * (tgl 1–7 = Minggu 1, tgl 8–14 = Minggu 2, dst). Null bila periode
     * tersebut bukan periode berjalan.
     */
    public static function currentWeek(Period $period, int $year): ?int
    {
        $now = CarbonImmutable::now();
        $start = self::periodStart($period, $year);
        $end = $start->addMonths(2);

        if ($now->lt($start) || $now->gte($end)) {
            return null;
        }

        return intdiv((int) $start->diffInDays($now), 7) + 1;
    }

    /**
     * Batas akhir PM untuk unit dengan `week_group` tertentu: akhir Minggu ke-N
     * pada periode, dijepit ke akhir periode (Minggu 9 bisa melewati 2 bulan).
     */
    public static function weekDeadline(Period $period, int $year, int $weekGroup): CarbonImmutable
    {
        $start = self::periodStart($period, $year);

        return $start->addDays(7 * max(1, $weekGroup))->min($start->addMonths(2));
    }

    public static function getPeriods(): array
    {
        return collect(Period::cases())->map(fn ($p) => [
            'value' => $p->value,
            'label' => $p->label(),
        ])->toArray();
    }

    /**
     * Daftar tahun yang bisa dipilih di filter (default 5 tahun terakhir).
     */
    public static function availableYears(int $count = 5): array
    {
        $current = self::currentYear();

        return range($current - max(0, $count - 1), $current);
    }

    /**
     * Daftar mesin aktif beserta jumlah PM yang sudah disetujui per periode.
     *
     * `$type` null memakai populasi Beranda (tanpa unit utility). Halaman
     * khusus utility mengirim `MachineType::Utility` supaya dot periode
     * mencerminkan kemajuan PM unit utility, bukan mesin.
     */
    public static function periodCompletion(int $year, ?MachineType $type = null): array
    {
        $activeIds = Machine::where('is_active', true)
            // Utility tidak ikut Beranda, jadi tidak masuk penyebut tunggakan
            // pada notifikasi periode.
            ->when(
                $type === null,
                fn ($q) => $q->where('type', '!=', MachineType::Utility->value),
                fn ($q) => $q->where('type', $type->value)
            )
            ->pluck('id');
        $total = $activeIds->count();

        $approved = [];

        if ($total > 0) {
            $approved = PmRecord::where('year', $year)
                ->whereIn('machine_id', $activeIds)
                ->where('status', PmStatus::Approved)
                ->selectRaw('period, COUNT(DISTINCT machine_id) AS cnt')
                ->groupBy('period')
                ->pluck('cnt', 'period')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        return [
            'total' => $total,
            'approved' => $approved,
        ];
    }

    /**
     * Dot notifikasi per periode. Warna ditentukan oleh posisi periode
     * terhadap periode berjalan (lihat resolveDot), bukan hanya oleh
     * kondisi periodenya sendiri.
     */
    public static function buildPeriods(int $year, ?MachineType $type = null): array
    {
        ['total' => $total, 'approved' => $approved] = self::periodCompletion($year, $type);

        $cases = Period::cases();
        $pending = [];

        foreach ($cases as $p) {
            $done = $approved[$p->value] ?? 0;
            $pending[$p->value] = $total > 0 ? max(0, $total - $done) : 0;
        }

        $currentYear = self::currentYear();
        $isCurrentYear = $year === $currentYear;

        // Hanya tahun berjalan yang punya "periode berjalan". Di tahun lain
        // semua periode adalah riwayat, jadi tidak ada yang boleh biru.
        $currentIndex = $isCurrentYear
            ? array_search(Period::current(), $cases, true)
            : null;

        return collect($cases)->map(function (Period $p, int $index) use ($pending, $total, $year, $currentIndex) {
            $locked = $p->isFuture($year);
            $isCurrent = $index === $currentIndex;

            return [
                'value' => $p->value,
                'label' => $p->label(),
                'locked' => $locked,
                'isCurrent' => $isCurrent,
                'done' => $total > 0 ? $total - $pending[$p->value] : 0,
                'total' => $total,
                'dot' => $locked
                    ? 'muted'
                    : self::resolveDot($index, $pending, $currentIndex),
            ];
        })->values()->toArray();
    }

    /**
     * Warna dot notifikasi:
     * - blue  : hanya periode berjalan, masih ada mesin belum selesai
     * - orange: periode sebelum berjalan masih ada tunggakan
     * - red   : tunggakan lebih lama dari periode sebelumnya
     * - green : tidak ada tunggakan sama sekali
     */
    private static function resolveDot(int $index, array $pending, ?int $currentIndex): string
    {
        $values = array_values($pending);

        // Di tahun selain tahun berjalan semua periode adalah riwayat:
        // tunggakan berarti merah, beres berarti hijau, tidak ada notion
        // "periode ini" maupun "periode sebelumnya".
        if ($currentIndex === null) {
            return $values[$index] > 0 ? 'red' : 'green';
        }

        $previous = $currentIndex - 1;

        // Periode berjalan masih ada mesin yang belum selesai.
        if ($index === $currentIndex) {
            return $values[$index] > 0 ? 'blue' : 'green';
        }

        // Periode ini masih ada mesin yang belum selesai. Kalau periode
        // lamanya, itu tunggakan: oranye untuk periode sebelumnya, merah
        // untuk yang lebih lama lagi.
        if ($values[$index] > 0) {
            return $index === $previous ? 'orange' : 'red';
        }

        // Periode ini beres, tapi periode sebelumnya belum.
        if ($previous >= 0 && $values[$previous] > 0 && $index > $previous) {
            return 'orange';
        }

        // Periode ini dan sebelumnya beres, tapi ada tunggakan lebih lama.
        // Catatan: batas slice harus di-clamp, karena array_slice dengan
        // panjang negatif akan mengambil semua elemen kecuali yang terakhir.
        foreach (array_slice($values, 0, max(0, $previous)) as $older) {
            if ($older > 0) {
                return 'red';
            }
        }

        return 'green';
    }
}
