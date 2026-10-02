<?php

namespace App\Enums;

/**
 * Tiga tahap rantai persetujuan PM.
 *
 * Rantainya berurutan dan tidak bisa dilompati:PM baru berstatus "Selesai"
 * setelah tahap Supervisor menandatangani.
 */
enum SignatureStage: string
{
    case Technician = 'technician';
    case Pic = 'pic';
    case Supervisor = 'supervisor';

    public function label(): string
    {
        return match ($this) {
            self::Technician => 'Tanda Tangan Teknisi/Pemeriksa',
            self::Pic => 'Persetujuan User PIC',
            self::Supervisor => 'Persetujuan Atasan',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Technician => 'Teknisi/Pemeriksa',
            self::Pic => 'User PIC',
            self::Supervisor => 'Atasan',
        };
    }

    /**
     * Peran yang menangani tahap ini. Admin boleh mengisi semua tahap karena
     * ia memegang seluruh permission.
     */
    public function role(): Role
    {
        return match ($this) {
            self::Technician => Role::Technician,
            self::Pic => Role::User,
            self::Supervisor => Role::Manager,
        };
    }

    /**
     * Permission yang mengizinkan penandatanganan pada tahap ini.
     */
    public function permission(): string
    {
        return match ($this) {
            self::Technician => 'pm.sign',
            self::Pic => 'pm.acknowledge',
            self::Supervisor => 'pm.approve',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Technician => 1,
            self::Pic => 2,
            self::Supervisor => 3,
        };
    }

    /**
     * Tahap dalam urutan eksekusi (bukan urutan enum).
     *
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return [self::Technician, self::Pic, self::Supervisor];
    }
}
