<?php

namespace App\Models;

use App\Enums\PmStatus;
use App\Enums\SignatureStage;
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

    public function signatures(): HasMany
    {
        return $this->hasMany(PmSignature::class);
    }

    public function signatureFor(SignatureStage $stage): ?PmSignature
    {
        return $this->signatures->firstWhere('stage', $stage);
    }

    /**
     * Alasan penolakan terakhir dari audit log.
     *
     * Alasan tidak disimpan di pm_records karena penolakan bisa berulang dan
     * riwayatnya memang sudah tercatat di audit_logs.
     *
     * `getAttribute()` dipanggil eksplisit, bukan `$log->changes`. Eloquent
     * punya properti internal `protected $changes` untuk dirty tracking; karena
     * method ini berada di dalam kelas model, PHP membaca properti protected
     * itu secara langsung dan `__get()` tidak pernah terpanggil. Hasilnya
     * array kosong, bukan kolom `changes` milik AuditLog.
     */
    public function rejectReason(): ?string
    {
        $log = AuditLog::query()
            ->where('action', 'pm.reject')
            ->where('subject_type', self::class)
            ->where('subject_id', $this->id)
            ->latest('created_at')
            ->first();

        $changes = $log?->getAttribute('changes');
        $reason = is_array($changes) ? ($changes['reason'] ?? null) : null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}