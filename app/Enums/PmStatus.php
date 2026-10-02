<?php

namespace App\Enums;

enum PmStatus: string
{
    /** Checklist sedang diisi, belum ada tanda tangan. */
    case Draft = 'draft';

    /** Teknisi sudah tanda tangan, menunggu User PIC. */
    case Submitted = 'submitted';

    /** User PIC sudah menyetujui, menunggu Atasan. */
    case PicApproved = 'pic_approved';

    /** Semua tahap selesai ditandatangani. */
    case Approved = 'approved';

    /** Ditolak di tengah rantai, dikembalikan ke teknisi untuk revisi. */
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Menunggu Tanda Tangan',
            self::Submitted => 'On Progress Approval by User PIC',
            self::PicApproved => 'On Progress Approval by Atasan',
            self::Approved => 'Selesai',
            self::Rejected => 'Perlu Revisi',
        };
    }

    /**
     * Versi pendek untuk pill/kartu, karena label() panjang dan dipakai untuk
     * tooltip serta laporan.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Draft => 'Belum Ditandatangani',
            self::Submitted, self::PicApproved => 'Sedang Approval',
            self::Approved => 'Selesai',
            self::Rejected => 'Perlu Revisi',
        };
    }

    /**
     * Tahap yang harus ditandatangani berikutnya, atau null kalau sudah final.
     */
    public function awaiting(): ?SignatureStage
    {
        return match ($this) {
            self::Draft, self::Rejected => SignatureStage::Technician,
            self::Submitted => SignatureStage::Pic,
            self::PicApproved => SignatureStage::Supervisor,
            self::Approved => null,
        };
    }

    /**
     * Tahap yang terakhir dilewati, dipakai untuk menandai centang pada
     * stepper persetujuan.
     */
    public function stageAfterApproval(): ?SignatureStage
    {
        return match ($this) {
            self::Submitted => SignatureStage::Technician,
            self::PicApproved => SignatureStage::Pic,
            self::Approved => SignatureStage::Supervisor,
            default => null,
        };
    }

    /** Status sudah final dan tidak bisa diubah lagi. */
    public function isFinal(): bool
    {
        return $this === self::Approved;
    }

    /** Sedang berjalan di rantai persetujuan (bukan belum dikerjakan). */
    public function isInApproval(): bool
    {
        return $this === self::Submitted || $this === self::PicApproved;
    }

    /**
     * Checklist masih boleh diubah. Setelah ditandatangani, isinya tidak bisa
     * diganti supaya persetujuan tidak bisa dilewati di belakang layar.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }
}
