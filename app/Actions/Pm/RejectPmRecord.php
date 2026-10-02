<?php

namespace App\Actions\Pm;

use App\Enums\PmStatus;
use App\Enums\SignatureStage;
use App\Models\PmRecord;
use App\Services\AuditLogService;
use App\Services\SignatureStorage;
use Illuminate\Support\Facades\DB;

/**
 * Menolak PM di tengah rantai persetujuan dan mengembalikannya ke teknisi
 * untuk revisi.
 *
 * Rejected kembali menunggu tahap Technician, dan SignPmRecord menimpa baris
 * signature tahap tekhnisi ketika ia menandatangani ulang. Semua signature
 * yang sudah ada ikut dibuang karena isinya sudah dianggap tidak valid.
 */
class RejectPmRecord
{
    public function handle(PmRecord $record, string $reason): PmRecord
    {
        $stage = $record->status->awaiting();

        if ($stage === null || $stage === SignatureStage::Technician) {
            throw new \LogicException('Hanya User PIC dan Atasan yang dapat menolak PM.');
        }

        return DB::transaction(function () use ($record, $stage, $reason) {
            // Hapus tanda tangan yang sudah ada: setelah ditolak, PM yang
            // ditandatangani bukan lagi versi yang sedang direview.
            $record->signatures->each(fn ($signature) => SignatureStorage::delete($signature->image_path));
            $record->signatures()->delete();

            $record->update([
                'status' => PmStatus::Rejected,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            AuditLogService::record(
                'pm.reject',
                PmRecord::class,
                $record->id,
                ['stage' => $stage->value, 'reason' => $reason]
            );

            return $record;
        });
    }
}
