<?php

namespace App\Models;

use App\Enums\MachineType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Template komponen: kumpulan Komponen (dari master Komponen / Parts) beserta
 * urutan dan Standar default, untuk Mesin atau Utility.
 *
 * Template diterapkan ke unit (lihat TemplateService): komponen unit diganti
 * dengan isi template. Unit tetap menyimpan komponennya sendiri, jadi template
 * yang kemudian diubah/dihapus tidak mengubah unit sampai diterapkan ulang.
 *
 * `machine_type` berisi tipe unit (MachineType). Template per-unit hasil
 * impor CSV utility memakai bentuk "utility:{kode unit}".
 */
class ChecklistTemplate extends Model
{
    use HasFactory;

    public const SCOPE_MACHINE = 'machine';

    public const SCOPE_UTILITY = 'utility';

    protected $fillable = ['machine_type', 'scope', 'name', 'description', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_template', 'template_id')
            ->withPivot('sort_no', 'standard')
            ->withTimestamps()
            ->orderByPivot('sort_no');
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class, 'template_id');
    }

    /**
     * Item teks bebas dari versi lama. Tidak dipakai lagi sejak template
     * berisi Komponen; disimpan untuk penelusuran data lama.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ChecklistTemplateItem::class, 'template_id')->orderBy('sort_no');
    }

    /** Template per-unit yang dibuat otomatis dari impor CSV utility. */
    public function isUnitSpecific(): bool
    {
        return str_starts_with((string) $this->machine_type, 'utility:');
    }

    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }

    public function typeLabel(): string
    {
        if ($this->isUnitSpecific()) {
            return 'Per-unit '.substr((string) $this->machine_type, strlen('utility:'));
        }

        return MachineType::tryFrom((string) $this->machine_type)?->label() ?? (string) $this->machine_type;
    }

    /**
     * Pilihan template untuk dropdown di form Mesin/Utility: template umum
     * dulu, lalu template per-unit.
     *
     * @return \Illuminate\Support\Collection<int, array{id:int, name:string, machine_type:string, is_default:bool, is_unit_specific:bool, components_count:int}>
     */
    public static function options(string $scope): \Illuminate\Support\Collection
    {
        return static::forScope($scope)
            ->withCount('components')
            ->orderByRaw("CASE WHEN machine_type LIKE 'utility:%' THEN 1 ELSE 0 END")
            ->orderBy('name')
            ->get()
            ->map(fn (ChecklistTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'machine_type' => $t->machine_type,
                'is_default' => $t->is_default,
                'is_unit_specific' => $t->isUnitSpecific(),
                'components_count' => $t->components_count,
            ])
            ->values();
    }
}
