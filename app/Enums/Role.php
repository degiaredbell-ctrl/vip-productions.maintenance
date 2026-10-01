<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Technician = 'technician';
    case User = 'user';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Technician => 'Teknisi',
            self::User => 'User',
            self::Viewer => 'Viewer',
        };
    }
}
