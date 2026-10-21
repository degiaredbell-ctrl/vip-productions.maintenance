<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris pengaturan aplikasi (key → value). Dibaca lewat
 * App\Services\Integration\IntegrationSettings, bukan langsung.
 */
class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'is_encrypted'];

    protected $casts = ['is_encrypted' => 'boolean'];
}
