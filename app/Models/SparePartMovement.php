<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu transaksi stok suku cadang: barang masuk ('in') atau keluar ('out').
 *
 * Transaksi keluar bisa dipakai sebagai catatan "pemakaian ke unit": kolom
 * `machine_id` (unit tujuan) dan `year`/`period` (periode PM) diisi, sehingga
 * satu tabel menjadi ledger stok sekaligus riwayat pemakaian.
 */
class SparePartMovement extends Model
{
    use HasFactory;

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    protected $fillable = [
        'spare_part_id', 'machine_id', 'user_id', 'type', 'qty',
        'moved_at', 'year', 'period', 'note',
    ];

    protected $casts = [
        'qty' => 'integer',
        'moved_at' => 'date',
        'year' => 'integer',
    ];

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    /**
     * Unit tujuan pemakaian (tabel `assets`); null untuk transaksi stok biasa.
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
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
