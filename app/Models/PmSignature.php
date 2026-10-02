<?php

namespace App\Models;

use App\Enums\SignatureStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tanda tangan (gambar canvas) pada satu tahap rantai persetujuan PM.
 *
 * Ditulis lewat tabel dengan satu baris per tahap, jadi menandatangani ulang
 * menimpa baris sebelumnya; file gambar lamanya dihapus oleh SignatureStorage.
 */
class PmSignature extends Model
{
    /** signed_at yang dipakai; tidak ada created_at/updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'pm_record_id', 'stage', 'signed_by', 'signed_by_name', 'signed_by_role',
        'image_path', 'note', 'signed_at',
    ];

    protected $casts = [
        'stage' => SignatureStage::class,
        'signed_at' => 'datetime',
    ];

    public function pmRecord(): BelongsTo
    {
        return $this->belongsTo(PmRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    /**
     * Gambar disajikan lewat route ber-otorisasi, bukan path mentah, supaya
     * nama file tidak bisa ditebak dari luar dan URL tidak bergantung APP_URL.
     */
    public function imageUrl(): string
    {
        return route('pm.signatures.show', [
            'record' => $this->pm_record_id,
            'stage' => $this->stage->value,
        ]);
    }
}
