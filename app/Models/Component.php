<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Master komponen/parts yang diperiksa saat Preventive Maintenance.
 *
 * Berbeda dari ChecklistTemplate lama yang dikelompokkan per tipe mesin, satu
 * komponen di sini adalah satu kesatuan yang bisa ditugaskan ke banyak Mesin
 * maupun unit Utility (relasi many-to-many lewat `component_machine`). Mengubah
 * komponen cukup sekali, dan semua unit yang memakainya ikut terbarui.
 */
class Component extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category', 'spec', 'sort_no'];

    protected $casts = ['sort_no' => 'integer'];

    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(Machine::class, 'component_machine')
            ->withPivot('sort_no', 'standard')
            ->withTimestamps();
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(ChecklistTemplate::class, 'component_template', 'component_id', 'template_id')
            ->withPivot('sort_no', 'standard')
            ->withTimestamps();
    }

    /**
     * Daftar master komponen untuk pemilih (multi-select) di halaman
     * Mesin/Utility. Dikirim sekali per halaman supaya modal "Edit Komponen"
     * bisa menyaring dan mencentang tanpa request tambahan.
     *
     * @return \Illuminate\Support\Collection<int, array{id:int, name:string, category:?string, spec:?string}>
     */
    public static function options(): \Illuminate\Support\Collection
    {
        return static::query()
            ->orderBy('category')
            ->orderBy('sort_no')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'spec'])
            ->map(fn (Component $component) => [
                'id' => $component->id,
                'name' => $component->name,
                'category' => $component->category,
                'spec' => $component->spec,
            ])
            ->values();
    }
}
