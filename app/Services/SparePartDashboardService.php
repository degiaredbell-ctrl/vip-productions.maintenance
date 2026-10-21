<?php

namespace App\Services;

use App\Enums\MachineType;
use App\Enums\Period;
use App\Models\SparePart;
use App\Models\SparePartMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Data Dashboard Suku Cadang: kondisi stok, kebutuhan restock, tren barang
 * masuk/keluar, dan pemakaian per suku cadang/unit.
 *
 * Dipakai halaman Dashboard Suku Cadang (/suku-cadang/dashboard) dan bagian
 * Suku Cadang di Beranda (/dashboard), jadi angka di kedua halaman selalu sama.
 *
 * Agregasi dilakukan di PHP (bukan SQL per-driver) supaya sama persis di MySQL
 * produksi dan SQLite test; volume transaksi dalam rentang beberapa bulan
 * cukup kecil untuk ini.
 */
class SparePartDashboardService
{
    public const RANGES = [3, 6, 12];

    public const DEFAULT_RANGE = 6;

    /** Batas hari tanpa pemakaian untuk dianggap "tidak bergerak". */
    public const SLOW_MOVING_DAYS = 180;

    /** Jumlah titik grafik garis per skala waktu. */
    public const LINE_POINTS = ['daily' => 30, 'weekly' => 12, 'monthly' => 12];

    public static function normalizeRange(mixed $range): int
    {
        $range = (int) $range;

        return in_array($range, self::RANGES, true) ? $range : self::DEFAULT_RANGE;
    }

    public static function build(int $range, bool $canManage = false): array
    {
        $range = self::normalizeRange($range);

        $today = CarbonImmutable::today();
        $from = $today->startOfMonth()->subMonths($range - 1);

        $parts = SparePart::orderBy('name')->orderBy('code')->get();

        $movements = SparePartMovement::query()
            ->where('moved_at', '>=', $from->toDateString())
            ->with(['sparePart:id,code,name,unit', 'machine:id,code,name,type'])
            ->get();

        $outs = $movements->where('type', SparePartMovement::TYPE_OUT);
        $ins = $movements->where('type', SparePartMovement::TYPE_IN);

        // Rata-rata pemakaian per bulan untuk perkiraan "stok cukup berapa lama".
        $usageByPart = $outs->groupBy('spare_part_id')->map(fn (Collection $rows) => (int) $rows->sum('qty'));

        $lastOutByPart = SparePartMovement::query()
            ->where('type', SparePartMovement::TYPE_OUT)
            ->selectRaw('spare_part_id, MAX(moved_at) as last_out')
            ->groupBy('spare_part_id')
            ->pluck('last_out', 'spare_part_id');

        $statusOf = fn (SparePart $part) => $part->stock <= 0 ? 'out' : ($part->isLowStock() ? 'low' : 'ok');

        $coverMonths = function (SparePart $part) use ($usageByPart, $range): ?float {
            $used = $usageByPart[$part->id] ?? 0;
            if ($used <= 0) {
                return null;
            }

            return round(max($part->stock, 0) / ($used / $range), 1);
        };

        $mapPart = fn (SparePart $part) => [
            'id' => $part->id,
            'code' => $part->code,
            'name' => $part->name,
            'category' => $part->category,
            'unit' => $part->unit,
            'stock' => $part->stock,
            'min_stock' => $part->min_stock,
            'location' => $part->location,
            'supplier' => $part->supplier,
            'status' => $statusOf($part),
        ];

        // Restock: habis dulu, lalu yang paling jauh di bawah minimum.
        $restock = $parts
            ->filter(fn (SparePart $part) => $statusOf($part) !== 'ok')
            ->sortBy([
                fn ($a, $b) => ($statusOf($a) === 'out' ? 0 : 1) <=> ($statusOf($b) === 'out' ? 0 : 1),
                fn ($a, $b) => ($a->stock - $a->min_stock) <=> ($b->stock - $b->min_stock),
            ])
            ->values()
            ->map(fn (SparePart $part) => [
                ...$mapPart($part),
                // Jumlah minimal yang perlu dipesan supaya kembali di atas batas minimum.
                'shortage' => max($part->min_stock - $part->stock + 1, 1),
                'used_in_range' => $usageByPart[$part->id] ?? 0,
                'cover_months' => $coverMonths($part),
            ]);

        $months = collect(range(0, $range - 1))->map(fn (int $i) => $from->addMonths($i));
        $trend = $months->map(function (CarbonImmutable $month) use ($ins, $outs) {
            $inMonth = fn (SparePartMovement $m) => $m->moved_at->format('Y-m') === $month->format('Y-m');

            return [
                'key' => $month->format('Y-m'),
                'label' => $month->locale('id')->translatedFormat('M y'),
                'in' => (int) $ins->filter($inMonth)->sum('qty'),
                'out' => (int) $outs->filter($inMonth)->sum('qty'),
            ];
        })->values();

        $topParts = $outs
            ->groupBy('spare_part_id')
            ->map(function (Collection $rows) use ($parts, $coverMonths) {
                $part = $parts->firstWhere('id', $rows->first()->spare_part_id);

                return [
                    'id' => $rows->first()->spare_part_id,
                    'code' => $rows->first()->sparePart?->code,
                    'name' => $rows->first()->sparePart?->name ?? '(dihapus)',
                    'unit' => $rows->first()->sparePart?->unit,
                    'qty' => (int) $rows->sum('qty'),
                    'count' => $rows->count(),
                    'stock' => $part?->stock,
                    'cover_months' => $part ? $coverMonths($part) : null,
                ];
            })
            ->sortByDesc('qty')
            ->take(10)
            ->values();

        $topUnits = $outs
            ->whereNotNull('machine_id')
            ->groupBy('machine_id')
            ->map(function (Collection $rows) {
                $machine = $rows->first()->machine;
                $isUtility = $machine?->type === MachineType::Utility->value;

                return [
                    'id' => $rows->first()->machine_id,
                    'code' => $machine?->code,
                    'name' => $machine?->name ?? '(unit dihapus)',
                    'category' => $isUtility ? 'utility' : 'machine',
                    'category_label' => $isUtility ? 'Utility' : 'Mesin',
                    'qty' => (int) $rows->sum('qty'),
                    'parts' => $rows->pluck('spare_part_id')->unique()->count(),
                ];
            })
            ->sortByDesc('qty')
            ->take(10)
            ->values();

        $categories = $parts
            ->groupBy(fn (SparePart $part) => $part->category ?: 'Tanpa kategori')
            ->map(fn (Collection $rows, string $name) => [
                'name' => $name,
                'total' => $rows->count(),
                'out' => $rows->filter(fn ($p) => $statusOf($p) === 'out')->count(),
                'low' => $rows->filter(fn ($p) => $statusOf($p) === 'low')->count(),
                'ok' => $rows->filter(fn ($p) => $statusOf($p) === 'ok')->count(),
            ])
            ->sortByDesc(fn ($c) => [$c['out'] + $c['low'], $c['total']])
            ->values();

        $slowSince = $today->subDays(self::SLOW_MOVING_DAYS);
        $slowMoving = $parts
            ->filter(function (SparePart $part) use ($lastOutByPart, $slowSince) {
                if ($part->stock <= 0) {
                    return false;
                }
                $last = $lastOutByPart[$part->id] ?? null;

                return $last === null || CarbonImmutable::parse($last)->lt($slowSince);
            })
            ->sortByDesc('stock')
            ->values();

        $recent = SparePartMovement::query()
            ->with(['sparePart:id,code,name,unit', 'machine:id,code,name', 'user:id,name'])
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (SparePartMovement $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'type_label' => $m->typeLabel(),
                'qty' => $m->qty,
                'date' => $m->moved_at?->locale('id')->translatedFormat('d M Y'),
                'part' => $m->sparePart ? "{$m->sparePart->code} — {$m->sparePart->name}" : '(dihapus)',
                'unit' => $m->sparePart?->unit,
                'machine' => $m->machine ? "{$m->machine->code} — {$m->machine->name}" : null,
                'period' => $m->period ? (Period::tryFrom($m->period)?->label() ?? $m->period).' '.$m->year : null,
                'user' => $m->user?->name,
                'note' => $m->note,
            ]);

        $monthStart = $today->startOfMonth();
        $thisMonth = $movements->filter(fn (SparePartMovement $m) => $m->moved_at->gte($monthStart));

        return [
            'range' => $range,
            'ranges' => self::RANGES,
            'slowMovingDays' => self::SLOW_MOVING_DAYS,
            'kpi' => [
                'total' => $parts->count(),
                'out' => $parts->filter(fn ($p) => $statusOf($p) === 'out')->count(),
                'low' => $parts->filter(fn ($p) => $statusOf($p) === 'low')->count(),
                'ok' => $parts->filter(fn ($p) => $statusOf($p) === 'ok')->count(),
                'no_min' => $parts->where('min_stock', 0)->count(),
                'stock_units' => (int) $parts->sum(fn ($p) => max($p->stock, 0)),
                'out_qty_month' => (int) $thisMonth->where('type', SparePartMovement::TYPE_OUT)->sum('qty'),
                'in_qty_month' => (int) $thisMonth->where('type', SparePartMovement::TYPE_IN)->sum('qty'),
                'out_qty_range' => (int) $outs->sum('qty'),
                'in_qty_range' => (int) $ins->sum('qty'),
                'slow_moving' => $slowMoving->count(),
                'units_served' => $outs->whereNotNull('machine_id')->pluck('machine_id')->unique()->count(),
            ],
            'restock' => $restock,
            'trend' => $trend,
            'lineTrend' => self::lineTrend($today),
            'topParts' => $topParts,
            'topUnits' => $topUnits,
            'categories' => $categories,
            'slowMoving' => $slowMoving->take(10)->map($mapPart)->values(),
            'recent' => $recent,
            'hasMovements' => SparePartMovement::query()->exists(),
            'can' => ['manage' => $canManage],
        ];
    }

    /**
     * Seri Barang Masuk & Barang Keluar untuk grafik garis, dalam tiga skala:
     * harian (30 hari), mingguan (12 minggu, Senin–Minggu), dan bulanan
     * (12 bulan). Ketiganya dikirim sekaligus supaya ganti skala di layar
     * instan tanpa memuat ulang halaman. Titik tanpa transaksi bernilai 0,
     * jadi garisnya tetap utuh.
     *
     * @return array<string, list<array{key: string, label: string, title: string, in: int, out: int}>>
     */
    public static function lineTrend(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        $buckets = [
            'daily' => collect(range(self::LINE_POINTS['daily'] - 1, 0))
                ->map(fn (int $i) => $today->subDays($i))
                ->map(fn (CarbonImmutable $d) => [
                    'start' => $d,
                    'end' => $d,
                    'label' => $d->format('d/m'),
                    'title' => $d->locale('id')->translatedFormat('l, d M Y'),
                ]),
            'weekly' => collect(range(self::LINE_POINTS['weekly'] - 1, 0))
                ->map(fn (int $i) => $today->startOfWeek(CarbonImmutable::MONDAY)->subWeeks($i))
                ->map(fn (CarbonImmutable $d) => [
                    'start' => $d,
                    'end' => $d->addDays(6),
                    'label' => $d->format('d/m'),
                    'title' => $d->locale('id')->translatedFormat('d M').' – '.$d->addDays(6)->locale('id')->translatedFormat('d M Y'),
                ]),
            'monthly' => collect(range(self::LINE_POINTS['monthly'] - 1, 0))
                ->map(fn (int $i) => $today->startOfMonth()->subMonths($i))
                ->map(fn (CarbonImmutable $d) => [
                    'start' => $d,
                    'end' => $d->endOfMonth()->startOfDay(),
                    'label' => $d->locale('id')->translatedFormat('M y'),
                    'title' => $d->locale('id')->translatedFormat('F Y'),
                ]),
        ];

        $earliest = collect($buckets)->map(fn (Collection $b) => $b->first()['start'])->min();

        // Dijumlahkan per tanggal sekali saja, lalu tiap skala tinggal
        // menjumlahkan tanggal yang masuk ke rentangnya.
        $perDay = SparePartMovement::query()
            ->where('moved_at', '>=', $earliest->toDateString())
            // Batas atas eksklusif (sebelum besok): di SQLite tanggal tersimpan
            // sebagai "Y-m-d 00:00:00", yang lebih besar dari string "Y-m-d".
            ->where('moved_at', '<', $today->addDay()->toDateString())
            ->get(['type', 'qty', 'moved_at'])
            ->groupBy(fn (SparePartMovement $m) => $m->moved_at->toDateString())
            ->map(fn (Collection $rows) => [
                'in' => (int) $rows->where('type', SparePartMovement::TYPE_IN)->sum('qty'),
                'out' => (int) $rows->where('type', SparePartMovement::TYPE_OUT)->sum('qty'),
            ]);

        return collect($buckets)->map(fn (Collection $points) => $points->map(function (array $p) use ($perDay) {
            $from = $p['start']->toDateString();
            $to = $p['end']->toDateString();
            $days = $perDay->filter(fn ($v, string $date) => $date >= $from && $date <= $to);

            return [
                'key' => $from,
                'label' => $p['label'],
                'title' => $p['title'],
                'in' => (int) $days->sum('in'),
                'out' => (int) $days->sum('out'),
            ];
        })->values()->all())->all();
    }
}
