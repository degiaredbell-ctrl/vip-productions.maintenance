<?php

namespace App\Actions\Pm;

use App\Enums\PmStatus;
use App\Enums\SignatureStage;
use App\Models\PmRecord;
use App\Models\PmSignature;
use App\Services\AuditLogService;
use App\Services\SignatureStorage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Satu tahap rantai persetujuan ditandatangani (dengan gambar tulisan tangan).
 *
 * Tahap ditentukan dari status record, bukan dari parameter: submitted berarti
 * PIC yang menandatangani, pic_approved berarti Atasan. Technician sering
 * menandatangani ulang setelah ditolak, jadi baris signature per tahap ditulis
 * ulang (updateOrCreate) dan file gambar lamanya dihapus.
 *
 * `$signerName` hanya dipakai pada tahap teknisi; tahap persetujuan memakai
 * nama akun login. Lihat `resolveSignerName()`.
 */
class SignPmRecord
{
    public function handle(PmRecord $record, string $dataUrl, ?string $note = null, ?string $signerName = null): PmSignature
    {
        $stage = $record->status->awaiting();

        if ($stage === null) {
            throw new \LogicException('Persetujuan PM ini sudah lengkap.');
        }

        // Penjaga kedua untuk kelengkapan checklist (yang pertama ada di
        // SignPmRecordRequest). Tanpa ini PM kosong bisa diteken dari pemanggil
        // lain dan diteruskan ke PIC.
        if ($stage === SignatureStage::Technician && ! $record->isChecklistComplete()) {
            throw new \LogicException('Checklist PM belum lengkap.');
        }

        $signedByName = $this->resolveSignerName($stage, $signerName);

        return DB::transaction(function () use ($record, $stage, $dataUrl, $note, $signedByName) {
            // File lama dihapus setelah yang baru berhasil ditulis, supaya
            // kegagalan saat menulis tidak menghilangkan tanda tangan lama.
            $previousPath = $record->signatures()
                ->where('stage', $stage->value)
                ->value('image_path');

            $path = SignatureStorage::store($record, $stage, $dataUrl);

            $signature = PmSignature::updateOrCreate(
                ['pm_record_id' => $record->id, 'stage' => $stage->value],
                [
                    'signed_by' => Auth::id(),
                    'signed_by_name' => $signedByName,
                    'signed_by_role' => Auth::user()?->roleLabel(),
                    'image_path' => $path,
                    'note' => $note,
                    'signed_at' => now(),
                ]
            );

            if ($previousPath !== null && $previousPath !== $path) {
                SignatureStorage::delete($previousPath);
            }

            $this->advance($record, $stage);

            AuditLogService::record(
                'pm.sign.' . $stage->value,
                PmRecord::class,
                $record->id,
                ['stage' => $stage->value, 'note' => $note]
            );

            return $signature;
        });
    }

    /**
     * Nama yang tersimpan di dokumen untuk satu tahap.
     *
     * Dua sumbernya memang sengaja dibedakan:
     *
     * - Technician menandatangani pekerjaan yang ia kerjakan sendiri, dan
     *   namanya diketik di form checklist. Account login tidak dipakai karena
     *   tidak selalu sama dengan nama yang ingin dicantumkan di dokumen.
     * - PIC/Atasan adalah approver yang sudah pasti login sebagai dirinya
     *   sendiri, jadi nama akun yang dipakai dan `signerName` dari client
     *   diabaikan. Kalau nama ikut dikirim, nilainya bisa dipalsukan agar
     *   dokumen menyatakan someone yang tidak menandatangani.
     */
    private function resolveSignerName(SignatureStage $stage, ?string $typedName): string
    {
        $accountName = trim((string) (Auth::user()?->name ?? ''));

        if ($stage !== SignatureStage::Technician && $accountName !== '') {
            return $accountName;
        }

        if (is_string($typedName) && trim($typedName) !== '') {
            return trim($typedName);
        }

        return $accountName !== '' ? $accountName : '-';
    }

    /**
     * Status setelah satu tahap selesai. Technician melepas kunci dengan
     * menandatangani; PIC hanya mengonfirmasi; Atasan menutup menjadi Selesai.
     */
    private function advance(PmRecord $record, SignatureStage $stage): void
    {
        match ($stage) {
            SignatureStage::Technician => $record->update([
                'status' => PmStatus::Submitted,
                'submitted_at' => $record->submitted_at ?? now(),
            ]),
            SignatureStage::Pic => $record->update([
                'status' => PmStatus::PicApproved,
            ]),
            SignatureStage::Supervisor => $record->update([
                'status' => PmStatus::Approved,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]),
        };
    }
}
