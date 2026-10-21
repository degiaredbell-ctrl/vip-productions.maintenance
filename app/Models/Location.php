<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Lokasi / Site unit Mesin & Utility, dikelola dari Settings → Lokasi / Site.
 *
 * `assets.location` menyimpan nama lokasi. Mengganti nama di sini ikut
 * memperbarui semua unit yang memakainya (lihat Admin\LocationController).
 */
class Location extends Model
{
    protected $fillable = ['name', 'description', 'sort_no'];

    protected $casts = ['sort_no' => 'integer'];

    public function machines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Machine::class, 'location', 'name');
    }

    /** @return Collection<int, string> */
    public static function names(): Collection
    {
        return static::query()->orderBy('sort_no')->orderBy('name')->pluck('name')->values();
    }
}
