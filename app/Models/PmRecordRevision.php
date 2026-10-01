<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmRecordRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['pm_record_id', 'revised_by', 'snapshot', 'reason', 'created_at'];

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function pmRecord(): BelongsTo
    {
        return $this->belongsTo(PmRecord::class);
    }

    public function reviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by');
    }
}
