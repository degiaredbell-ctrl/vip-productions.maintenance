<?php

namespace App\Models;

use App\Enums\MachineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Tipe Mesin (tabel `machine_types`), dikelola dari Settings → Tipe Mesin.
 *
 * `assets.type` dan `checklist_templates.machine_type` menyimpan `code`, jadi
 * kode bersifat tetap setelah dibuat; yang bisa diubah adalah nama tampilnya.
 * Tipe sistem (`utility`, `generic`) tidak bisa dihapus karena dipakai
 * aplikasi: `utility` memisahkan halaman Utility, `generic` tipe cadangan.
 *
 * Nama App\Enums\MachineType tetap dipakai untuk kode sistem di kode program.
 */
class AssetType extends Model
{
    protected $table = 'machine_types';

    protected $fillable = ['code', 'name', 'description', 'is_system', 'sort_no'];

    protected $casts = ['is_system' => 'boolean', 'sort_no' => 'integer'];

    private const CACHE_KEY = 'asset-type-labels';

    protected static function booted(): void
    {
        // Label di-cache per request (lihat labels()); perubahan master harus
        // langsung terlihat di request yang sama.
        static::saved(fn () => app()->forgetInstance(self::CACHE_KEY));
        static::deleted(fn () => app()->forgetInstance(self::CACHE_KEY));
    }

    public function machines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Machine::class, 'type', 'code');
    }

    /** @return array<string, string> code => nama */
    public static function labels(): array
    {
        if (! app()->bound(self::CACHE_KEY)) {
            app()->instance(self::CACHE_KEY, static::query()->pluck('name', 'code')->all());
        }

        return app(self::CACHE_KEY);
    }

    public static function labelFor(?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        return self::labels()[$code] ?? MachineType::tryFrom($code)?->label() ?? $code;
    }

    /**
     * Pilihan tipe untuk dropdown/filter.
     *
     * @param  bool|null  $utility  true = hanya Utility, false = semua kecuali Utility, null = semua
     * @return Collection<int, array{value:string, label:string}>
     */
    public static function options(?bool $utility = null): Collection
    {
        return static::query()
            ->when($utility === true, fn ($q) => $q->where('code', MachineType::Utility->value))
            ->when($utility === false, fn ($q) => $q->where('code', '!=', MachineType::Utility->value))
            ->orderBy('sort_no')
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (AssetType $t) => ['value' => $t->code, 'label' => $t->name])
            ->values();
    }
}
