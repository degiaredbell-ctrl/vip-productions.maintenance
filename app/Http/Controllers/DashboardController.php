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
        $period = Period::tryFrom($request->query('period', Period::current()->value)) ?? Period::current();
        $year = (int) $request->query('year', now()->year);
        $search = $request->query('q', '');
        $status = $request->query('status', 'all');

        $machines = Machine::with(['template.items', 'pmRecords' => function ($q) use ($year, $period) {
            $q->where('year', $year)->where('period', $period->value);
        }])
            ->where('is_active', true)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_no')
            ->get()
            ->map(function ($machine) use ($year, $period, $status) {
                $record = $machine->pmRecords->first();
                $displayStatus = $this->getDisplayStatus($record);

                if ($status !== 'all' && $displayStatus !== $status) {
                    return null;
                }

                return [
                    'id' => $machine->id,
                    'code' => $machine->code,
                    'name' => $machine->name,
                    'type' => $machine->type,
                    'week_group' => $machine->week_group,
                    'status' => $displayStatus,
                    'record_id' => $record?->id,
                    'record_status' => $record?->status->value,
                ];
            })
            ->filter()
            ->values();

        $stats = [
            'total' => $machines->count(),
            'done' => $machines->where('status', 'done')->count(),
            'todo' => $machines->where('status', 'todo')->count(),
            'issue' => $machines->where('status', 'issue')->count(),
        ];

        return Inertia::render('Dashboard/Index', [
            'machines' => $machines,
            'stats' => $stats,
            'periods' => PeriodService::getPeriods(),
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
