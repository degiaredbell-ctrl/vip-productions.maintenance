<?php

namespace App\Http\Controllers;

use App\Enums\MachineType;
use App\Enums\Period;
use App\Models\AssetType;
use App\Models\Machine;
use App\Models\SparePart;
use App\Models\SparePartMovement;
use App\Services\AuditLogService;
use App\Services\PeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman "Form Maintenance - Suku Cadang": catat pemakaian suku cadang ke unit
 * (Mesin/Utility) per periode PM, sekaligus menampilkan riwayatnya.
 *
 * Pemakaian disimpan sebagai transaksi stok keluar pada tabel yang sama dengan
 * barang masuk/keluar (`spare_part_movements`) — kolom `machine_id`, `year`,
 * dan `period` menandai tujuan pemakaian. Stok terkini otomatis berkurang.
 *
 * Semua user login boleh melihat; hanya pemegang `sparepart.manage`
 * (Admin & tech-all) yang boleh mencatat pemakaian.
 */
class SparePartUsageController extends Controller
{
    public function index(Request $request): Response
    {
        $years = PeriodService::availableYears();
        $year = (int) $request->query('year', PeriodService::currentYear());
        if (! in_array($year, $years, true)) {
            $year = PeriodService::currentYear();
        }

        $periodValues = collect(Period::cases())->map(fn (Period $p) => $p->value)->all();
        $period = (string) $request->query('period', 'all');
        if ($period !== 'all' && ! in_array($period, $periodValues, true)) {
            $period = 'all';
        }

        $units = Machine::where('is_active', true)
            ->orderBy('name')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (Machine $machine) => [
                'id' => $machine->id,
                'code' => $machine->code,
                'name' => $machine->name,
                'type' => $machine->type,
                'type_label' => AssetType::labelFor((string) $machine->type) ?: 'Umum',
            ])
            ->values();

        $parts = SparePart::orderBy('name')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'unit', 'stock'])
            ->map(fn (SparePart $part) => [
                'id' => $part->id,
                'code' => $part->code,
                'name' => $part->name,
                'unit' => $part->unit,
                'stock' => $part->stock,
            ])
            ->values();

        $usages = SparePartMovement::query()
            ->whereNotNull('machine_id')
            ->where('year', $year)
            ->when($period !== 'all', fn ($query) => $query->where('period', $period))
            ->with([
                'sparePart:id,code,name,unit',
                'machine:id,code,name,type',
                'user:id,name',
            ])
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (SparePartMovement $movement) => [
                'id' => $movement->id,
                'qty' => $movement->qty,
                'unit_label' => $movement->sparePart?->unit,
                'moved_at' => $movement->moved_at?->format('d M Y'),
                'year' => $movement->year,
                'period' => $movement->period,
                'period_label' => $this->periodLabel($movement->period),
                'note' => $movement->note,
                'part' => $movement->sparePart ? [
                    'id' => $movement->sparePart->id,
                    'code' => $movement->sparePart->code,
                    'name' => $movement->sparePart->name,
                ] : null,
                'machine' => $movement->machine ? [
                    'id' => $movement->machine->id,
                    'code' => $movement->machine->code,
                    'name' => $movement->machine->name,
                ] : null,
                'user_name' => $movement->user?->name,
            ]);

        return Inertia::render('FormMaintenance/SukuCadang', [
            'units' => $units,
            'parts' => $parts,
            'periods' => PeriodService::getPeriods(),
            'years' => $years,
            'year' => $year,
            'period' => $period,
            'currentPeriod' => Period::current()->value,
            'usages' => $usages,
            'can' => [
                'manage' => $request->user()->can('sparepart.manage'),
            ],
        ]);
    }

    /**
     * Catat pemakaian: buat transaksi stok keluar bertujuan unit + periode, lalu
     * kurangi stok terkini. Ditolak bila jumlah melebihi stok tersedia.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'machine_id' => ['required', 'integer', 'exists:assets,id'],
            'spare_part_id' => ['required', 'integer', 'exists:spare_parts,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period' => ['required', Rule::in(collect(Period::cases())->map(fn (Period $p) => $p->value)->all())],
            'qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'used_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $part = SparePart::findOrFail($validated['spare_part_id']);
        $machine = Machine::find($validated['machine_id']);
        $qty = (int) $validated['qty'];

        if ($qty > $part->stock) {
            throw ValidationException::withMessages([
                'qty' => "Jumlah pemakaian ({$qty}) melebihi stok tersedia ({$part->stock}).",
            ]);
        }

        DB::transaction(function () use ($part, $validated, $qty) {
            SparePartMovement::create([
                'spare_part_id' => $part->id,
                'machine_id' => $validated['machine_id'],
                'user_id' => auth()->id(),
                'type' => SparePartMovement::TYPE_OUT,
                'qty' => $qty,
                'moved_at' => $validated['used_at'] ?? now()->toDateString(),
                'year' => $validated['year'],
                'period' => $validated['period'],
                'note' => $validated['note'] ?? null,
            ]);

            $part->stock -= $qty;
            $part->save();
        });

        AuditLogService::record('sparepart.movement', SparePart::class, $part->id, [
            'code' => $part->code,
            'type' => SparePartMovement::TYPE_OUT,
            'qty' => $qty,
            'stock' => $part->stock,
            'machine' => $machine?->code,
            'year' => $validated['year'],
            'period' => $validated['period'],
            'usage' => true,
        ]);

        $periodLabel = $this->periodLabel($validated['period']);
        $unitName = $machine?->name ?? 'unit';

        // Arahkan kembali ke tahun/periode catatan supaya baris baru langsung
        // terlihat di riwayat (filter halaman bisa berbeda dari periode input).
        return redirect()
            ->route('form-maintenance.suku-cadang', [
                'year' => $validated['year'],
                'period' => $validated['period'],
            ])
            ->with('success', "Pemakaian {$qty} {$part->unit} {$part->code} untuk {$unitName} ({$periodLabel} {$validated['year']}) dicatat. Stok kini {$part->stock}.");
    }

    private function periodLabel(?string $period): string
    {
        if ($period === null || $period === '') {
            return '—';
        }

        return Period::tryFrom($period)?->label() ?? $period;
    }
}
