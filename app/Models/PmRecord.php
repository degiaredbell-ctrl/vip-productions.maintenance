<?php

namespace App\Models;

use App\Enums\PmStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id', 'year', 'period', 'technician_id', 'status',
        'general_note', 'revision_count', 'submitted_at', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'status' => PmStatus::class,
        'year' => 'integer',
        'revision_count' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PmRecordItem::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PmRecordRevision::class)->latest();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
