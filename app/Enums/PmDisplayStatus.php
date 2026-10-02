<?php

namespace App\Enums;

/**
 * Status yang ditampilkan di Beranda dan Riwayat.
 *
 * Berbeda dengan PmStatus: beberapa status internal dipetakan ke kategori
 * yang sama supaya kartu mesin tidak harus menampilkan detail rantai approval.
 */
enum PmDisplayStatus: string
{
    case Todo = 'todo';
    case Progress = 'progress';
    case Done = 'done';
    case Issue = 'issue';

    public static function fromPmStatus(PmStatus $status): self
    {
        return match ($status) {
            PmStatus::Approved => self::Done,
            PmStatus::Rejected => self::Issue,
            // Menunggu tanda tangan teknisi: pekerjaan lapangan masih berjalan.
            PmStatus::Draft => self::Todo,
            // Pekerjaan lapangan sudah selesai, tinggal menunggu approval —
            // bukan kendala, tapi juga belum boleh dihitung "Selesai" oleh
            // logika dot dan angka kepatuhan laporan.
            PmStatus::Submitted, PmStatus::PicApproved => self::Progress,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'Belum',
            self::Progress => 'Sedang Approval',
            self::Done => 'Selesai',
            self::Issue => 'Perlu Revisi',
        };
    }

    /** Variant warna untuk NeuPill. */
    public function variant(): string
    {
        return match ($this) {
            self::Todo => 'todo',
            self::Progress => 'progress',
            self::Done => 'done',
            self::Issue => 'issue',
        };
    }
}
