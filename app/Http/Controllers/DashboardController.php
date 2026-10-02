<?php

namespace App\Http\Controllers;

use App\Enums\Period;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Services\PeriodService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $years = PeriodService::availableYears();
        $year = (int) $request->query('year', now()->year);

        // Tahun di luar daftar filter dianggap tidak valid agar tidak membuka
        // periode yang seharusnya masih terkunci (mis. ?year=abc -> 0).
        if (!in_array($year, $years, true)) {
            $year = now()->year;
        }

        $period = Period::tryFrom($request->query('period', Period::current()->value)) ?? Period::current();
        $search = $request->query('q', '');
        $status = $request->query('status', 'all');

        // Periode yang belum tiba dikunci: kembalikan ke periode berjalan
        // supaya URL lama atau tautan langsung tidak membuka periode terkunci.
        if ($period->isFuture($year)) {
            $period = Period::current();
        }

        $machines = Machine::with(['template.items', 'pmRecords' => function ($q) use ($year, $period) {
            $q->where('year', $year)->where('period', $period->value);
        }])
            ->where('is_active', true)
            ->orderBy('sort_no')
            ->get()
            ->map(function ($machine) {
                $record = $machine->pmRecords->first();

                return [
                    'id' => $machine->id,
                    'code' => $machine->code,
                    'name' => $machine->name,
                    'type' => $machine->type,
                    'week_group' => $machine->week_group,
                    'status' => $this->getDisplayStatus($record),
                    'record_id' => $record?->id,
                    'record_status' => $record?->status->value,
                ];
            })
            ->values();

        // Statistik memakai seluruh mesin aktif pada periode ini, bukan hasil
        // filter, supaya angka ringkasan dan dot notifikasi tetap konsisten
        // saat user memfilter status atau mengetik pencarian.
        $stats = [
            'total' => $machines->count(),
            'done' => $machines->where('status', 'done')->count(),
            'todo' => $machines->where('status', 'todo')->count(),
            'issue' => $machines->where('status', 'issue')->count(),
        ];

        $needle = mb_strtolower(trim($search));

        $visible = $machines
            ->when($status !== 'all', fn ($c) => $c->where('status', $status))
            ->when($needle !== '', fn ($c) => $c->filter(fn ($m) =>
                str_contains(mb_strtolower($m['code']), $needle)
                || str_contains(mb_strtolower($m['name']), $needle)
            ))
            ->values();

        return Inertia::render('Dashboard/Index', [
            'machines' => $visible,
            'stats' => $stats,
            'periods' => PeriodService::buildPeriods($year),
            'years' => $years,
            'currentPeriod' => $period->value,
            'currentYear' => $year,
            'search' => $search,
            'statusFilter' => $status,
            'isFuturePeriod' => PeriodService::isFuturePeriod($period, $year),
        ]);
    }

    private function getDisplayStatus(?PmRecord $record): string
    {
        if (!$record) return 'todo';
        if ($record->status->value === 'approved') return 'done';
        if ($record->status->value === 'rejected') return 'issue';
        if ($record->status->value === 'submitted') return 'todo';
        return 'todo';
    }
}
