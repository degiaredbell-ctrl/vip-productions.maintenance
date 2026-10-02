<?php

namespace App\Http\Controllers;

use App\Actions\Pm\ApprovePmRecord;
use App\Actions\Pm\SubmitPmRecord;
use App\Enums\Period;
use App\Http\Requests\StorePmRecordRequest;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Services\PeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PmRecordController extends Controller
{
    public function create(Request $request, Machine $machine): Response
    {
        $period = Period::tryFrom($request->query('period', Period::current()->value)) ?? Period::current();
        $year = (int) $request->query('year', now()->year);

        $existing = PmRecord::with('items')
            ->where('machine_id', $machine->id)
            ->where('year', $year)
            ->where('period', $period->value)
            ->first();

        $template = $machine->template ?? $machine->type ? \App\Models\ChecklistTemplate::where('machine_type', $machine->type)->where('is_default', true)->first() : null;

        $items = $existing ? $existing->items->map(function ($item) {
            return [
                'item_name' => $item->item_name,
                'category' => $item->category,
                'spec' => $item->spec,
                'actual' => $item->actual,
                'act_clean' => $item->act_clean,
                'act_repair' => $item->act_repair,
                'act_lubricate' => $item->act_lubricate,
                'act_replace' => $item->act_replace,
                'final_condition' => $item->final_condition,
                'parts_replaced' => $item->parts_replaced,
            ];
        })->toArray() : ($template ? $template->items->map(function ($item) {
            return [
                'item_name' => $item->name,
                'category' => $item->category,
                'spec' => $item->spec,
                'actual' => '',
                'act_clean' => false,
                'act_repair' => false,
                'act_lubricate' => false,
                'act_replace' => false,
                'final_condition' => '',
                'parts_replaced' => 0,
            ];
        })->toArray() : []);

        // Filter yang sedang aktif di Beranda ikut diteruskan, supaya tombol
        // "Kembali" dan redirect setelah simpan tidak membuat user kehilangan
        // filter periode/area/status/pencarian yang sedang dipilih.
        $dashboardUrl = route('dashboard', array_filter([
            'period' => $period->value,
            'year' => $year,
            'sub' => $request->query('sub'),
            'status' => $request->query('status'),
            'q' => $request->query('q'),
        ], fn ($value) => $value !== null && $value !== '' && $value !== 'all'));

        // Disimpan lewat session, bukan diambil dari input form, supaya URL
        // tujuan redirect tidak bisa dipakai untuk open redirect.
        $request->session()->put('pm_dashboard_url', $dashboardUrl);

        return Inertia::render('Pm/Form', [
            'machine' => [
                'id' => $machine->id,
                'code' => $machine->code,
                'name' => $machine->name,
                'location' => $machine->location,
                'category' => $machine->category,
                'sub_category' => $machine->sub_category,
                'type' => $machine->type,
                'week_group' => $machine->week_group,
            ],
            'items' => $items,
            'period' => $period->value,
            'year' => $year,
            'dashboardUrl' => $dashboardUrl,
            'existing' => $existing ? [
                'id' => $existing->id,
                'status' => $existing->status->value,
                'revision_count' => $existing->revision_count,
                'general_note' => $existing->general_note,
            ] : null,
            'isFuturePeriod' => PeriodService::isFuturePeriod($period, $year),
            'canFill' => $request->user()->can('pm.fill'),
        ]);
    }

    public function store(StorePmRecordRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $record = app(SubmitPmRecord::class)->handle($data);

        // Kembali ke Beranda dengan filter yang tadi sedang aktif.
        return redirect()
            ->to($request->session()->pull('pm_dashboard_url', route('dashboard')))
            ->with('success', 'Checklist PM berhasil disimpan.');
    }

    public function approve(Request $request, PmRecord $record): RedirectResponse
    {
        $this->authorize('approve', $record);

        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        app(ApprovePmRecord::class)->handle($record, $validated['approved'], $validated['note'] ?? null);

        return redirect()->back()->with('success', $validated['approved'] ? 'PM disetujui.' : 'PM ditolak.');
    }
}
