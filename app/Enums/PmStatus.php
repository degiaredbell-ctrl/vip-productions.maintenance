<?php

namespace App\Enums;

enum PmStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Terkirim',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }
}
