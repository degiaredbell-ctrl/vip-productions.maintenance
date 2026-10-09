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
            ->withPivot('sort_no')
            ->withTimestamps();
    }
}
