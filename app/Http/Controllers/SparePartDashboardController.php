<?php

namespace App\Http\Controllers;

use App\Enums\MachineType;
use App\Enums\Period;
use App\Models\Machine;
use App\Models\SparePart;
use App\Models\SparePartMovement;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard monitoring Suku Cadang: kondisi stok, kebutuhan restock, tren
 * barang masuk/keluar, dan pemakaian per suku cadang/unit.
 *
 * Agregasi dilakukan di PHP (bukan SQL per-driver) supaya sama persis di MySQL
 * produksi dan SQLite test; volume transaksi dalam rentang beberapa bulan
 * cukup kecil untuk ini.
 */
class SparePartDashboardController extends Controller
{
    private const RANGES = [3, 6, 12];

    /** Batas hari tanpa pemakaian untuk dianggap "tidak bergerak". */
    private const SLOW_MOVING_DAYS = 180;

    public function index(Request $request): Response
    {
        $range = (int) $request->query('range', 6);
        if (! in_array($range, self::RANGES, true)) {
            $range = 6;
        }

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
                'label' => $month->translatedFormat('M y'),
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
                'date' => $m->moved_at?->translatedFormat('d M Y'),
                'part' => $m->sparePart ? "{$m->sparePart->code} — {$m->sparePart->name}" : '(dihapus)',
                'unit' => $m->sparePart?->unit,
                'machine' => $m->machine ? "{$m->machine->code} — {$m->machine->name}" : null,
                'period' => $m->period ? (Period::tryFrom($m->period)?->label() ?? $m->period).' '.$m->year : null,
                'user' => $m->user?->name,
                'note' => $m->note,
            ]);

        $monthStart = $today->startOfMonth();
        $thisMonth = $movements->filter(fn (SparePartMovement $m) => $m->moved_at->gte($monthStart));

        return Inertia::render('SpareParts/Dashboard', [
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
            'topParts' => $topParts,
            'topUnits' => $topUnits,
            'categories' => $categories,
            'slowMoving' => $slowMoving->take(10)->map($mapPart)->values(),
            'recent' => $recent,
            'hasMovements' => SparePartMovement::query()->exists(),
            'can' => ['manage' => $request->user()->can('sparepart.manage')],
        ]);
    }
}
