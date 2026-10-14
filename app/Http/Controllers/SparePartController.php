<?php

namespace App\Http\Controllers;

use App\Models\SparePart;
use App\Models\SparePartMovement;
use App\Services\AuditLogService;
use App\Services\SparePartImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman "Suku Cadang": master suku cadang beserta transaksi stok
 * (barang masuk/keluar).
 *
 * Daftar bersifat global (tidak terikat unit Mesin/Utility). Semua user yang
 * login boleh melihat; hanya pemegang `sparepart.manage` (Admin & tech-all)
 * yang bisa menambah/mengubah/menghapus, mencatat transaksi, dan mengunggah CSV.
 */
class SparePartController extends Controller
{
    private const MANAGED_FIELDS = [
        'code', 'category', 'name', 'brand', 'type', 'description',
        'unit', 'qty', 'stock', 'stock_start', 'min_stock',
        'supplier', 'needed_for', 'note', 'location_code', 'location',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $parts = SparePart::orderBy('category')
            ->orderBy('name')
            ->orderBy('code')
            ->get()
            ->map(fn (SparePart $part) => $this->mapPart($part));

        return Inertia::render('SpareParts/Index', [
            'parts' => $parts,
            'categories' => SparePart::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values()
                ->all(),
            'locations' => SparePart::query()
                ->whereNotNull('location')
                ->distinct()
                ->orderBy('location')
                ->pluck('location')
                ->values()
                ->all(),
            'stats' => [
                'total' => $parts->count(),
                'low' => $parts->where('low_stock', true)->count(),
                'out' => $parts->where('stock', '<=', 0)->count(),
            ],
            'can' => [
                'manage' => $user->can('sparepart.manage'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPart(SparePart $part): array
    {
        return [
            'id' => $part->id,
            'code' => $part->code,
            'category' => $part->category,
            'name' => $part->name,
            'brand' => $part->brand,
            'type' => $part->type,
            'description' => $part->description,
            'unit' => $part->unit,
            'qty' => $part->qty,
            'stock' => $part->stock,
            'stock_start' => $part->stock_start,
            'min_stock' => $part->min_stock,
            'supplier' => $part->supplier,
            'needed_for' => $part->needed_for,
            'note' => $part->note,
            'location_code' => $part->location_code,
            'location' => $part->location,
            'last_modified' => $part->last_modified,
            'low_stock' => $part->isLowStock(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePart($request);

        $part = SparePart::create([
            ...$data,
            'sort_no' => ((int) SparePart::withTrashed()->max('sort_no')) + 1,
        ]);

        AuditLogService::record('sparepart.create', SparePart::class, $part->id, [
            'code' => $part->code,
            'name' => $part->name,
            'fields' => $part->only(self::MANAGED_FIELDS),
        ]);

        return back()->with('success', "Suku cadang {$part->code} — {$part->name} berhasil ditambahkan.");
    }

    public function update(Request $request, SparePart $sparePart): RedirectResponse
    {
        $data = $this->validatePart($request, $sparePart->id);
        $before = $sparePart->only(self::MANAGED_FIELDS);

        $sparePart->fill($data)->save();

        $changes = [];
        foreach ($data as $key => $value) {
            if ((string) ($before[$key] ?? '') !== (string) $sparePart->{$key}) {
                $changes[$key] = ['dari' => $before[$key] ?? null, 'ke' => $sparePart->{$key}];
            }
        }

        if ($changes !== []) {
            AuditLogService::record('sparepart.update', SparePart::class, $sparePart->id, [
                'code' => $sparePart->code,
                'changed' => $changes,
            ]);
        }

        return back()->with('success', "Suku cadang {$sparePart->code} berhasil diperbarui.");
    }

    public function destroy(Request $request, SparePart $sparePart): RedirectResponse
    {
        $snapshot = $sparePart->only(['code', 'name']);
        $movements = $sparePart->movements()->count();

        $sparePart->delete();

        AuditLogService::record('sparepart.delete', SparePart::class, $sparePart->id, [
            ...$snapshot,
            'movements' => $movements,
        ]);

        return back()->with('success', "Suku cadang {$snapshot['code']} dihapus dari daftar.");
    }

    /**
     * Hapus massal: menerima daftar id, soft delete semuanya, dan mencatatnya
     * di audit log sebagai satu aksi `sparepart.delete` dengan penanda bulk.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $parts = SparePart::whereIn('id', $validated['ids'])
            ->get(['id', 'code', 'name']);

        if ($parts->isEmpty()) {
            return back()->with('error', 'Tidak ada suku cadang yang dipilih.');
        }

        $items = $parts->map(fn (SparePart $part) => [
            'id' => $part->id,
            'code' => $part->code,
            'name' => $part->name,
        ])->all();

        SparePart::whereIn('id', $parts->pluck('id'))->delete();

        AuditLogService::record('sparepart.delete', SparePart::class, null, [
            'bulk' => true,
            'count' => count($items),
            'items' => $items,
        ]);

        return back()->with('success', count($items).' suku cadang dihapus dari daftar.');
    }

    public function restore(Request $request, int $sparePart): RedirectResponse
    {
        $trashed = SparePart::onlyTrashed()->find($sparePart);

        if (! $trashed) {
            return back()->with('error', 'Suku cadang tersebut tidak ada di daftar yang dihapus.');
        }

        $trashed->restore();

        AuditLogService::record('sparepart.restore', SparePart::class, $trashed->id, [
            'code' => $trashed->code,
            'name' => $trashed->name,
        ]);

        return back()->with('success', "Suku cadang {$trashed->code} berhasil dipulihkan.");
    }

    /**
     * Catat transaksi stok (barang masuk/keluar) lalu sesuaikan stok terkini.
     */
    public function storeMovement(Request $request, SparePart $sparePart): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([SparePartMovement::TYPE_IN, SparePartMovement::TYPE_OUT])],
            'qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'moved_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $type = $validated['type'];
        $qty = (int) $validated['qty'];

        if ($type === SparePartMovement::TYPE_OUT && $qty > $sparePart->stock) {
            throw ValidationException::withMessages([
                'qty' => "Jumlah keluar ({$qty}) melebihi stok tersedia ({$sparePart->stock}).",
            ]);
        }

        DB::transaction(function () use ($sparePart, $validated, $type, $qty) {
            SparePartMovement::create([
                'spare_part_id' => $sparePart->id,
                'user_id' => auth()->id(),
                'type' => $type,
                'qty' => $qty,
                'moved_at' => $validated['moved_at'] ?? now()->toDateString(),
                'note' => $validated['note'] ?? null,
            ]);

            $sparePart->stock += $type === SparePartMovement::TYPE_IN ? $qty : -$qty;
            $sparePart->save();
        });

        $label = $type === SparePartMovement::TYPE_IN ? 'masuk' : 'keluar';

        AuditLogService::record('sparepart.movement', SparePart::class, $sparePart->id, [
            'code' => $sparePart->code,
            'type' => $type,
            'qty' => $qty,
            'stock' => $sparePart->stock,
        ]);

        return back()->with('success', "Barang {$label} {$qty} {$sparePart->unit} untuk {$sparePart->code} dicatat. Stok kini {$sparePart->stock}.");
    }

    /**
     * Riwayat transaksi satu suku cadang (dipanggil dari modal di halaman).
     */
    public function movements(Request $request, SparePart $sparePart): JsonResponse
    {
        $movements = $sparePart->movements()
            ->with('user:id,name')
            ->limit(100)
            ->get()
            ->map(fn (SparePartMovement $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'type_label' => $m->typeLabel(),
                'qty' => $m->qty,
                'moved_at' => $m->moved_at?->format('d M Y'),
                'note' => $m->note,
                'user_name' => $m->user?->name,
            ]);

        return response()->json([
            'part' => [
                'id' => $sparePart->id,
                'code' => $sparePart->code,
                'name' => $sparePart->name,
                'unit' => $sparePart->unit,
                'stock' => $sparePart->stock,
            ],
            'movements' => $movements,
        ]);
    }

    /**
     * Unggah CSV untuk memperbarui/menambah master suku cadang.
     */
    public function import(Request $request, SparePartImportService $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:8192'],
        ]);

        $path = $request->file('file')->getRealPath();

        $report = $importer->importFromPath($path);

        AuditLogService::record('sparepart.import', SparePart::class, null, [
            'created' => $report['created'],
            'updated' => $report['updated'],
            'total' => $report['total'],
            'skipped' => $report['skipped'],
        ]);

        return back()->with('success', "Impor selesai: {$report['created']} baru, {$report['updated']} diperbarui dari {$report['total']} baris.".($report['skipped'] > 0 ? " {$report['skipped']} baris dilewati." : ''));
    }

    /**
     * Unduh template CSV (header saja) untuk diisi lalu diunggah kembali lewat
     * `POST /suku-cadang/import`.
     *
     * Header memakai urutan 21 kolom nyata yang dikenali SparePartImportService
     * (lihat konstanta COLUMNS di service tersebut — pemetaan mengikuti posisi,
     * bukan nama). Saat file hasil isian diunggah, baris header ini otomatis
     * dilewati karena memuat "Code.asset".
     */
    public function template(): StreamedResponse
    {
        $columns = [
            'Code.asset', 'CATEGORY DEVICE', 'Suku Cadang', 'MEREK', 'TYPE', 'Desc',
            'Last - Modified', 'Ket. Last - Modified', 'UNIT', 'QTY', 'STOK AKHIR',
            'BARANG KELUAR', 'Log.Barang Keluar', 'BARANG MASUK', 'STOK AWAL', 'MIN',
            'SUPLIER', 'Kebutuhan Untuk', 'Penjelasan', 'Code.loc', 'LOC',
        ];

        return response()->streamDownload(function () use ($columns) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 supaya Excel membaca karakter khusus dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns);

            fclose($handle);
        }, 'template-suku-cadang.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePart(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('spare_parts', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['nullable', 'string', 'max:30'],
            'qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'stock' => ['nullable', 'integer', 'min:-1000000', 'max:1000000'],
            'stock_start' => ['nullable', 'integer', 'min:-1000000', 'max:1000000'],
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'needed_for' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'location_code' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        // Kolom integer selalu dikirim eksplisit supaya mengosongkan isian
        // tidak menghasilkan NULL di kolom NOT NULL.
        foreach (['qty', 'stock', 'stock_start', 'min_stock'] as $column) {
            $data[$column] = (int) ($data[$column] ?? 0);
        }

        return $data;
    }
}
