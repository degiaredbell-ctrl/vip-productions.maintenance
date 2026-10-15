<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    protected $fillable = ['filename', 'size', 'type', 'status', 'error', 'created_by'];

    protected $casts = [
        'size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Ukuran file dalam satuan yang mudah dibaca (KB/MB/GB).
     */
    public function humanSize(): string
    {
        $size = (int) $this->size;

        if ($size >= 1073741824) {
            return number_format($size / 1073741824, 2, ',', '.').' GB';
        }

        if ($size >= 1048576) {
            return number_format($size / 1048576, 2, ',', '.').' MB';
        }

        if ($size >= 1024) {
            return number_format($size / 1024, 1, ',', '.').' KB';
        }

        return $size.' B';
    }

    public function typeLabel(): string
    {
        return $this->type === 'scheduled' ? 'Terjadwal' : 'Manual';
    }
}
