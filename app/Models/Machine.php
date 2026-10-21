<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'assets';

    protected $fillable = [
        'code', 'name', 'location', 'category', 'sub_category',
        'type', 'week_group', 'template_id', 'is_active', 'sort_no',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'week_group' => 'integer',
        'sort_no' => 'integer',
        'pic_user_id' => 'integer',
    ];

    /**
     * User PIC penanggung jawab perangkat (Settings → Notifications → PIC
     * Perangkat). Membatasi notifikasi User/PIC ke perangkatnya sendiri dan
     * menjadi pilihan awal PIC di form PM.
     */
    public function picUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function template(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }

    /**
     * Komponen/parts yang diperiksa untuk unit ini. Relasi baru pengganti
     * template: komponen memakai tabel master tersendiri dan diikat ke unit
     * lewat pivot, sehingga satu komponen bisa dipakai banyak unit.
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_machine')
            ->withPivot('sort_no', 'standard')
            ->orderBy('component_machine.sort_no')
            ->withTimestamps();
    }

    public function pmRecords(): HasMany
    {
        return $this->hasMany(PmRecord::class);
    }

    /**
     * Komponen yang benar-benar dipakai form PM untuk unit ini: master komponen
     * (pivot `component_machine`) bila sudah ditugaskan, atau item template lama
     * sebagai cadangan untuk data yang belum ikut termigrasi. Halaman Mesin dan
     * Utility memakai ini supaya daftar yang tampil sama dengan checklist yang
     * akan diisi teknisi.
     *
     * `standard` adalah nilai acuan PER UNIT (kolom pivot `standard`), sedangkan
     * `spec` adalah standar bawaan master komponen. UI memakai
     * `standard ?? spec` sehingga unit yang belum diisi standar tetap menampilkan
     * spesifikasi master.
     *
     * @return \Illuminate\Support\Collection<int, array{id:?int, name:string, category:?string, standard:?string, spec:?string}>
     */
    public function effectiveComponents(): \Illuminate\Support\Collection
    {
        if ($this->components->isNotEmpty()) {
            return $this->components
                ->map(fn (Component $component) => [
                    'id' => $component->id,
                    'name' => $component->name,
                    'category' => $component->category,
                    'standard' => $component->pivot->standard,
                    'spec' => $component->spec,
                ])
                ->values();
        }

        // Cadangan: unit belum punya komponen sendiri, pakai isi templatenya.
        // `id` dikosongkan karena komponen ini belum benar-benar ditugaskan.
        return ($this->template?->components ?? collect())
            ->map(fn (Component $component) => [
                'id' => null,
                'name' => $component->name,
                'category' => $component->category,
                'standard' => $component->pivot->standard,
                'spec' => $component->spec,
            ])
            ->values();
    }

    public function currentPmRecord(): HasOne
    {
        return $this->hasOne(PmRecord::class)->latest();
    }
}
