<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu transaksi stok suku cadang: barang masuk ('in') atau keluar ('out').
 */
class SparePartMovement extends Model
{
    use HasFactory;

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    protected $fillable = ['spare_part_id', 'user_id', 'type', 'qty', 'moved_at', 'note'];

    protected $casts = [
        'qty' => 'integer',
        'moved_at' => 'date',
    ];

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_IN ? 'Masuk' : 'Keluar';
    }
}
