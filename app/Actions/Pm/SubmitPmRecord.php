<?php

namespace App\Actions\Pm;

use App\Enums\PmStatus;
use App\Models\PmRecord;
use App\Models\PmRecordItem;
use App\Models\PmRecordRevision;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
                    'status' => PmStatus::Submitted,
                    'general_note' => $data['general_note'] ?? null,
                    'submitted_at' => now(),
                ]
            );

            // If this is a revision, save snapshot of old items
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

            // Replace items
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

            return $record->load('items');
        });
    }
}
