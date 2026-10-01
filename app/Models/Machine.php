<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'type', 'week_group', 'template_id', 'is_active', 'sort_no',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'week_group' => 'integer',
        'sort_no' => 'integer',
    ];

    public function template(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }

    public function pmRecords(): HasMany
    {
        return $this->hasMany(PmRecord::class);
    }

    public function currentPmRecord(): HasOne
    {
        return $this->hasOne(PmRecord::class)->latest();
    }
}
