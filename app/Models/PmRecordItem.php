<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmRecordItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pm_record_id', 'item_name', 'category', 'spec', 'actual',
        'act_clean', 'act_repair', 'act_lubricate', 'act_replace',
        'final_condition', 'parts_replaced',
    ];

    protected $casts = [
        'act_clean' => 'boolean',
        'act_repair' => 'boolean',
        'act_lubricate' => 'boolean',
        'act_replace' => 'boolean',
        'parts_replaced' => 'integer',
    ];

    public function pmRecord(): BelongsTo
    {
        return $this->belongsTo(PmRecord::class);
    }
}
