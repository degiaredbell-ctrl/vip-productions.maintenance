<?php

namespace App\Http\Controllers;

use App\Enums\MachineType;
use App\Enums\SignatureStage;
use App\Http\Requests\StoreMachineRequest;
use App\Http\Requests\UpdateMachineRequest;
use App\Models\AssetType;
use App\Models\ChecklistTemplate;
use App\Models\Component;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Models\User;
use App\Services\AssetScope;
use App\Services\AuditLogService;
use App\Services\TemplateService;
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
        $machines = Machine::with(['template.components', 'components'])
            // Halaman ini khusus mesin produksi; utility dikelola lewat
            // halaman "Utility" terpisah.
            ->where('type', '!=', MachineType::Utility->value)
            ->orderBy('sort_no')
            ->orderBy('code')
            ->get()
            ->map(function ($m) {
                // Komponen efektif: master komponen yang ditugaskan ke unit ini
                // (pivot), atau item template lama bila belum termigrasi —
                // sumber yang sama dengan form PM.
                $components = $m->effectiveComponents();

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
                    'component_count' => $components->count(),
                    'components' => $components,
                    // Id komponen yang benar-benar ditugaskan (pivot) dipakai
                    // modal "Edit Komponen" untuk menandai centang awal.
                    'component_ids' => $m->components->pluck('id')->all(),
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

        $user = $request->user();

        return Inertia::render('Machines/Index', [
            'machines' => $machines,
            'allComponents' => Component::options(),
            'trashed' => $trashed,
            'types' => AssetType::options(utility: false),
            'templates' => ChecklistTemplate::options(ChecklistTemplate::SCOPE_MACHINE),
            'locations' => Location::names(),
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
            'can' => [
                'fill' => $user->can('pm.fill'),
                'history' => $user->can('pm.history.view'),
                // Halaman ini sendiri sudah dibatasi `machine.manage` atau
                // `machine.view`, jadi `manage` dikirim eksplisit supaya
                // kontrol CRUD di UI digantung pada izin yang benar (mis.
                // tech-machines bisa membuka halaman tanpa tombol tambah/ubah).
                'manage' => $user->can('machine.manage'),
            ],
        ]);
    }

    /**
     * Izin kelola aset dibedakan per tipe (mesin vs utility) supaya satu
     * controller tetap bisa dipakai oleh halaman Mesin dan Utility tanpa
     * membocorkan CRUD antar-scope. Tipe utility butuh `utility.manage`,
     * selain itu butuh `machine.manage`.
     */
    private function authorizeType(User $user, string $type): void
    {
        $permission = $type === MachineType::Utility->value ? 'utility.manage' : 'machine.manage';

        abort_unless($user->can($permission), 403);
    }

    public function store(StoreMachineRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Izin disesuaikan dengan tipe aset, bukan dengan route, supaya
        // seseorang tidak bisa membuat unit utility lewat endpoint mesin
        // (atau sebaliknya) hanya karena memegang salah satu permission.
        $this->authorizeType($request->user(), $data['type'] ?? MachineType::Generic->value);

        // Urutan tampil mengikuti sort_no. Nilai mesin baru otomatis ditaruh di
        // akhir. Mesin yang sudah dihapus tetap dihitung supaya nomor urutnya
        // tidak bertabrakan kalau mesin itu dipulihkan kembali.
        $data['sort_no'] = ((int) Machine::withTrashed()->max('sort_no')) + 1;

        $machine = Machine::create($data);

        // Template yang dipilih langsung mengisi komponen unit baru.
        $applied = $this->applyChosenTemplate($machine);

        AuditLogService::record('machine.create', Machine::class, $machine->id, [
            'code' => $machine->code,
            'name' => $machine->name,
            'fields' => $machine->only(self::MANAGED_FIELDS),
        ]);

        return back()->with('success', $applied
            ? "Mesin {$machine->code} berhasil ditambahkan dengan komponen dari template \"{$applied}\"."
            : "Mesin {$machine->code} berhasil ditambahkan.");
    }

    public function update(UpdateMachineRequest $request, Machine $machine): RedirectResponse
    {
        $this->authorizeType($request->user(), $machine->type);

        $data = $request->validated();
        $before = $machine->only(self::MANAGED_FIELDS);

        // sort_no tidak ada di payload yang divalidasi, jadi urutan daftar tetap
        // seperti saat mesin dibuat.
        $machine->fill($data)->save();

        // Ganti template = komponen unit diganti dengan isi template baru.
        // Menyimpan tanpa mengganti template tidak menyentuh komponen.
        $applied = (string) ($before['template_id'] ?? '') !== (string) ($machine->template_id ?? '')
            ? $this->applyChosenTemplate($machine)
            : null;

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

        return back()->with('success', $applied
            ? "Mesin {$machine->code} berhasil diperbarui. Komponen diganti dari template \"{$applied}\"."
            : "Mesin {$machine->code} berhasil diperbarui.");
    }

    /**
     * Terapkan template yang dipilih di form ke unit. Template hanya diterapkan
     * bila cakupannya cocok (Mesin ↔ Utility); mengembalikan nama template
     * yang diterapkan, atau null.
     */
    private function applyChosenTemplate(Machine $machine): ?string
    {
        $template = $machine->template_id ? ChecklistTemplate::find($machine->template_id) : null;

        if (! $template) {
            return null;
        }

        $isUtility = $machine->type === MachineType::Utility->value;
        $expected = $isUtility ? ChecklistTemplate::SCOPE_UTILITY : ChecklistTemplate::SCOPE_MACHINE;

        if ($template->scope !== $expected) {
            return null;
        }

        TemplateService::applyToMachine($template, $machine);

        AuditLogService::record('template.apply', ChecklistTemplate::class, $template->id, [
            'name' => $template->name,
            'units' => [$machine->code],
        ]);

        return $template->name;
    }

    public function destroy(Request $request, Machine $machine): RedirectResponse
    {
        $this->authorizeType($request->user(), $machine->type);

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
        // Route model binding tidak pernah mengambil model yang soft-deleted,
        // jadi pencarian dilakukan manual lewat onlyTrashed().
        $trashed = Machine::onlyTrashed()->find($machine);

        if (! $trashed) {
            return back()->with('error', 'Mesin tersebut tidak ada di daftar mesin yang dihapus.');
        }

        $this->authorizeType($request->user(), $trashed->type);

        $trashed->restore();

        AuditLogService::record('machine.restore', Machine::class, $trashed->id, [
            'code' => $trashed->code,
            'name' => $trashed->name,
        ]);

        return back()->with('success', "Mesin {$trashed->code} berhasil dipulihkan.");
    }

    /**
     * Simpan komponen + standar per unit dari halaman Mesin/Utility.
     *
     * Penugasan ditulis utuh (sync), jadi menghapus centang otomatis melepas
     * komponen. `standard` kosong disimpan NULL supaya form PM/daftar jatuh ke
     * spesifikasi master komponen.
     */
    public function updateComponents(Request $request, Machine $machine): RedirectResponse
    {
        $this->authorizeType($request->user(), $machine->type);

        $validated = $request->validate([
            'items' => ['array'],
            'items.*.id' => ['required', 'integer', 'exists:components,id'],
            'items.*.standard' => ['nullable', 'string', 'max:255'],
        ]);

        $sync = [];
        $order = 1;

        foreach ($validated['items'] ?? [] as $item) {
            $standard = trim((string) ($item['standard'] ?? ''));

            $sync[(int) $item['id']] = [
                'sort_no' => $order++,
                'standard' => $standard !== '' ? $standard : null,
            ];
        }

        $machine->components()->sync($sync);

        AuditLogService::record('machine.components.update', Machine::class, $machine->id, [
            'code' => $machine->code,
            'name' => $machine->name,
            'components' => collect($sync)->map(fn ($row, $id) => [
                'id' => $id,
                'standard' => $row['standard'],
            ])->values()->all(),
        ]);

        $count = count($sync);

        return back()->with('success', "Komponen {$machine->code} — {$machine->name} diperbarui ({$count} komponen).");
    }

    public function history(Request $request, Machine $machine): Response
    {
        // Riwayat dipakai ulang untuk mesin dan utility (lewat halaman yang
        // berbeda), jadi scope tipe aset tetap ditegakkan di sini.
        abort_unless(
            AssetScope::allows($request->user(), $machine),
            403,
            'Akses tidak sesuai ruang lingkup role Anda.',
        );

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
                            'replace_note' => $item->replace_note,
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
            'returnTo' => $request->query('return'),
        ]);
    }
}
