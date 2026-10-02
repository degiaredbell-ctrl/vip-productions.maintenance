<?php

namespace App\Actions\Pm;

use App\Enums\PmStatus;
use App\Models\PmRecord;
use App\Models\PmRecordItem;
use App\Models\PmRecordRevision;
use App\Services\AuditLogService;
use App\Services\SignatureStorage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan checklist PM oleh teknisi/pemeriksa.
 *
 * Menyimpan checklist tidak lagi langsung mengirim ke approval: statusnya tetap
 * Draft supaya teknisi masih bisa memperbaiki. Yang melepas kunci dan
 * menaikkan status ke Submitted adalah signature tangannya (SignPmRecord).
 *
 * Setiap penyimpanan membatalkan signature yang sudah ada, karena isinya yang
 * direview approval harus sama persis dengan checklist yang tersimpan.
 */
class SubmitPmRecord
{
    public function handle(array $data): PmRecord
    {
        return DB::transaction(function () use ($data) {
            $record = PmRecord::updateOrCreate(
                [
                    'machine_id' => $data['machine_id'],
                    'year' => $data['year'],
                    'period' => $data['period'],
                ],
                [
                    'technician_id' => Auth::id(),
                    'status' => PmStatus::Draft,
                    'general_note' => $data['general_note'] ?? null,
                    'submitted_at' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                ]
            );

            // Simpan snapshot item lama supaya riwayat revisi tetap ada.
            if ($record->wasRecentlyCreated === false && $record->items()->exists()) {
                $oldItems = $record->items->map(function ($item) {
                    return $item->only(['item_name', 'category', 'spec', 'actual', 'act_clean', 'act_repair', 'act_lubricate', 'act_replace', 'final_condition', 'parts_replaced']);
                })->toArray();

                PmRecordRevision::create([
                    'pm_record_id' => $record->id,
                    'revised_by' => Auth::id(),
                    'snapshot' => $oldItems,
                    'reason' => $data['revision_reason'] ?? null,
                    'created_at' => now(),
                ]);

                $record->increment('revision_count');
            }

            // Checklist berubah, jadi tanda tangan yang sudah ada tidak lagi
            // relevan dan harus diulang dari tahap pertama.
            $record->signatures->each(fn ($signature) => SignatureStorage::delete($signature->image_path));
            $record->signatures()->delete();

            $record->items()->delete();
            foreach ($data['items'] as $item) {
                PmRecordItem::create([
                    'pm_record_id' => $record->id,
                    'item_name' => $item['item_name'],
                    'category' => $item['category'],
                    'spec' => $item['spec'] ?? null,
                    'actual' => $item['actual'],
                    'act_clean' => $item['act_clean'] ?? false,
                    'act_repair' => $item['act_repair'] ?? false,
                    'act_lubricate' => $item['act_lubricate'] ?? false,
                    'act_replace' => $item['act_replace'] ?? false,
                    'final_condition' => $item['final_condition'] ?? null,
                    'parts_replaced' => $item['parts_replaced'] ?? 0,
                ]);
            }

            AuditLogService::record('pm.submit', PmRecord::class, $record->id);

            return $record->load(['items', 'signatures']);
        });
    }
}
