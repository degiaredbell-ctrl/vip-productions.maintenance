<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMachineRequest;
use App\Http\Requests\UpdateMachineRequest;
use App\Models\Machine;
use App\Models\PmRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MachineController extends Controller
{
    public function index(Request $request): Response
    {
        $machines = Machine::with('template')
            ->orderBy('sort_no')
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'code' => $m->code,
                    'name' => $m->name,
                    'type' => $m->type,
                    'week_group' => $m->week_group,
                    'is_active' => $m->is_active,
                    'template_name' => $m->template?->name,
                ];
            });

        return Inertia::render('Machines/Index', [
            'machines' => $machines,
            'types' => collect(\App\Enums\MachineType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->toArray(),
        ]);
    }

    public function store(StoreMachineRequest $request): RedirectResponse
    {
        $data = $request->validated();
        Machine::create($data);
        return redirect()->back()->with('success', 'Mesin berhasil ditambahkan.');
    }

    public function update(UpdateMachineRequest $request, Machine $machine): RedirectResponse
    {
        $data = $request->validated();
        $machine->update($data);
        return redirect()->back()->with('success', 'Mesin berhasil diperbarui.');
    }

    public function destroy(Request $request, Machine $machine): RedirectResponse
    {
        $machine->delete();
        return redirect()->back()->with('success', 'Mesin dinonaktifkan.');
    }

    public function history(Request $request, Machine $machine): Response
    {
        $records = PmRecord::with(['technician', 'items'])
            ->where('machine_id', $machine->id)
            ->orderByDesc('year')
            ->orderByDesc('period')
            ->limit(18)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'year' => $r->year,
                    'period' => $r->period,
                    'status' => $r->status->value,
                    'status_label' => $r->status->label(),
                    'technician_name' => $r->technician?->name,
                    'revision_count' => $r->revision_count,
                    'submitted_at' => $r->submitted_at?->format('d M Y'),
                    'approved_at' => $r->approved_at?->format('d M Y'),
                    'items' => $r->items->map(function ($item) {
                        return [
                            'item_name' => $item->item_name,
                            'category' => $item->category,
                            'actual' => $item->actual,
                            'act_clean' => $item->act_clean,
                            'act_repair' => $item->act_repair,
                            'act_lubricate' => $item->act_lubricate,
                            'act_replace' => $item->act_replace,
                            'final_condition' => $item->final_condition,
                            'parts_replaced' => $item->parts_replaced,
                        ];
                    }),
                ];
            });

        return Inertia::render('Machines/History', [
            'machine' => [
                'id' => $machine->id,
                'code' => $machine->code,
                'name' => $machine->name,
                'type' => $machine->type,
            ],
            'records' => $records,
        ]);
    }
}
