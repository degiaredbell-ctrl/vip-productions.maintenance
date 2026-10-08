<?php

namespace App\Http\Controllers;

use App\Enums\MachineType;
use App\Enums\SignatureStage;
use App\Http\Requests\StoreMachineRequest;
use App\Http\Requests\UpdateMachineRequest;
use App\Models\ChecklistTemplate;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MachineController extends Controller
{
    /**
     * Kolom mesin yang dikelola dari halaman "Kelola Mesin". Dipakai juga untuk
     * memetakan nilai yang berubah ke audit log supaya jejaknya terbaca.
     */
    private const MANAGED_FIELDS = [
        'code', 'name', 'location', 'category', 'sub_category',
        'type', 'week_group', 'template_id', 'is_active',
    ];

    public function index(Request $request): Response
    {
        $machines = Machine::with('template')
            // Halaman ini khusus mesin produksi; utility dikelola lewat
            // halaman "Utility" terpisah.
            ->where('type', '!=', MachineType::Utility->value)
            ->orderBy('sort_no')
            ->orderBy('code')
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'code' => $m->code,
                    'name' => $m->name,
                    'location' => $m->location,
                    'category' => $m->category,
                    'sub_category' => $m->sub_category,
                    'type' => $m->type,
                    'week_group' => $m->week_group,
                    'is_active' => $m->is_active,
                    'template_id' => $m->template_id,
                    'template_name' => $m->template?->name,
                ];
            });

        // Mesin yang dihapus tetap bisa dipulihkan, jadi daftarnya ikut dikirim
        // supaya UI bisa menawarkan tombol "Pulihkan" di halaman yang sama.
        $trashed = Machine::onlyTrashed()
            ->where('type', '!=', MachineType::Utility->value)
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'deleted_at' => $m->deleted_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('Machines/Index', [
            'machines' => $machines,
            'trashed' => $trashed,
            'types' => collect(MachineType::cases())
                ->reject(fn ($t) => $t === MachineType::Utility)
                ->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])
                ->values()
                ->toArray(),
            'templates' => ChecklistTemplate::orderBy('name')
                // Template per-unit utility ("utility:{kode}") dibuat dari CSV,
                // bukan milik halaman mesin ini.
                ->where('machine_type', 'NOT LIKE', 'utility:%')
                ->get(['id', 'name', 'machine_type'])
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'machine_type' => $t->machine_type]),
            'categories' => Machine::whereNotNull('category')
                ->where('type', '!=', MachineType::Utility->value)
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values()
                ->all(),
            'subCategories' => Machine::whereNotNull('sub_category')
                ->where('type', '!=', MachineType::Utility->value)
                ->distinct()
                ->orderBy('sub_category')
                ->pluck('sub_category')
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreMachineRequest $request): RedirectResponse
    {
        $this->authorize('create', Machine::class);

        $data = $request->validated();

        // Urutan tampil mengikuti sort_no. Nilai mesin baru otomatis ditaruh di
        // akhir. Mesin yang sudah dihapus tetap dihitung supaya nomor urutnya
        // tidak bertabrakan kalau mesin itu dipulihkan kembali.
        $data['sort_no'] = ((int) Machine::withTrashed()->max('sort_no')) + 1;

        $machine = Machine::create($data);

        AuditLogService::record('machine.create', Machine::class, $machine->id, [
            'code' => $machine->code,
            'name' => $machine->name,
            'fields' => $machine->only(self::MANAGED_FIELDS),
        ]);

        return back()->with('success', "Mesin {$machine->code} berhasil ditambahkan.");
    }

    public function update(UpdateMachineRequest $request, Machine $machine): RedirectResponse
    {
        $this->authorize('update', $machine);

        $data = $request->validated();
        $before = $machine->only(self::MANAGED_FIELDS);

        // sort_no tidak ada di payload yang divalidasi, jadi urutan daftar tetap
        // seperti saat mesin dibuat.
        $machine->fill($data)->save();

        $changes = [];
        foreach ($data as $key => $value) {
            if ((string) $before[$key] !== (string) $machine->{$key}) {
                $changes[$key] = ['dari' => $before[$key], 'ke' => $machine->{$key}];
            }
        }

        if ($changes !== []) {
            AuditLogService::record('machine.update', Machine::class, $machine->id, [
                'code' => $machine->code,
                'changed' => $changes,
            ]);
        }

        return back()->with('success', "Mesin {$machine->code} berhasil diperbarui.");
    }

    public function destroy(Request $request, Machine $machine): RedirectResponse
    {
        $this->authorize('delete', $machine);

        // Soft delete: riwayat PM beserta tanda tangannya tetap utuh dan mesin
        // masih bisa dipulihkan, jadi menghapus mesin tidak menghapus data.
        $recordCount = $machine->pmRecords()->count();
        $snapshot = $machine->only(['code', 'name', 'location', 'is_active']);

        $machine->delete();

        AuditLogService::record('machine.delete', Machine::class, $machine->id, [
            ...$snapshot,
            'pm_records' => $recordCount,
        ]);

        return back()->with('success', $recordCount > 0
            ? "Mesin {$snapshot['code']} dihapus dari daftar. {$recordCount} riwayat PM tetap tersimpan dan mesin bisa dipulihkan."
            : "Mesin {$snapshot['code']} dihapus dari daftar.");
    }

    public function restore(Request $request, int $machine): RedirectResponse
    {
        $this->authorize('restore', Machine::class);

        // Route model binding tidak pernah mengambil model yang soft-deleted,
        // jadi pencarian dilakukan manual lewat onlyTrashed().
        $trashed = Machine::onlyTrashed()->find($machine);

        if (! $trashed) {
            return back()->with('error', 'Mesin tersebut tidak ada di daftar mesin yang dihapus.');
        }

        $trashed->restore();

        AuditLogService::record('machine.restore', Machine::class, $trashed->id, [
            'code' => $trashed->code,
            'name' => $trashed->name,
        ]);

        return back()->with('success', "Mesin {$trashed->code} berhasil dipulihkan.");
    }

    public function history(Request $request, Machine $machine): Response
    {
        $records = PmRecord::with(['technician', 'items', 'signatures'])
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
                    'technician_name' => $r->technicianName(),
                    'revision_count' => $r->revision_count,
                    'submitted_at' => $r->submitted_at?->format('d M Y'),
                    'approved_at' => $r->approved_at?->format('d M Y'),
                    'reject_reason' => $r->status->isFinal() ? null : $r->rejectReason(),
                    'signatures' => collect(SignatureStage::ordered())
                        ->map(fn (SignatureStage $stage) => [
                            'stage' => $stage->value,
                            'label' => $stage->shortLabel(),
                            'signed_by_name' => $r->signatureFor($stage)?->signed_by_name,
                            'signed_at' => $r->signatureFor($stage)?->signed_at?->format('d M Y H:i'),
                        ])
                        ->values()
                        ->all(),
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
                'location' => $machine->location,
                'category' => $machine->category,
                'sub_category' => $machine->sub_category,
                'type' => $machine->type,
                'week_group' => $machine->week_group,
            ],
            'records' => $records,
        ]);
    }
}
