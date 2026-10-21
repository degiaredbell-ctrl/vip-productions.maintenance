<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master suku cadang (spare part).
 *
 * Bersifat global: tidak terikat ke satu unit Mesin/Utility. Kolom mengikuti
 * CSV references/suku_cadang.csv (lihat SparePartImportService).
 */
class SparePart extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'category', 'name', 'brand', 'type', 'description',
        'last_modified', 'last_modified_note', 'unit', 'qty',
        'stock', 'stock_out', 'stock_out_log', 'stock_in', 'stock_start',
        'min_stock', 'supplier', 'needed_for', 'note',
        'location_code', 'location', 'sort_no',
    ];

    protected $casts = [
        'qty' => 'integer',
        'stock' => 'integer',
        'stock_out' => 'integer',
        'stock_in' => 'integer',
        'stock_start' => 'integer',
        'min_stock' => 'integer',
        'sort_no' => 'integer',
    ];

    protected static function booted(): void
    {
        // Peringatan email (Settings → Integrate) hanya saat stok MELEWATI
        // batas: aman → menipis/habis, atau menipis → habis. Transaksi yang
        // membuat stok tetap di bawah batas tidak mengirim email lagi.
        static::updated(function (SparePart $part) {
            if (! $part->wasChanged('stock')) {
                return;
            }

            $before = self::levelOf((int) $part->getOriginal('stock'), (int) $part->getOriginal('min_stock'));
            $after = self::levelOf($part->stock, $part->min_stock);

            if ($after > $before) {
                app(\App\Services\Integration\MailNotifier::class)->lowStock($part);
            }
        });
    }

    /** 0 = aman, 1 = menipis, 2 = habis. */
    private static function levelOf(int $stock, int $min): int
    {
        if ($stock <= 0) {
            return 2;
        }

        return $min > 0 && $stock <= $min ? 1 : 0;
    }

    public function movements(): HasMany
    {
        return $this->hasMany(SparePartMovement::class)->orderByDesc('moved_at')->orderByDesc('id');
    }

    /**
     * Stok berada di atau di bawah batas minimum (dan minimum memang diatur).
     */
    public function isLowStock(): bool
    {
        return $this->min_stock > 0 && $this->stock <= $this->min_stock;
    }
}
