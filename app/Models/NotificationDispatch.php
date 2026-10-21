<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali pengiriman notifikasi (terjadwal, manual, atau event approval).
 * Dipakai sebagai riwayat di Settings → Notifications dan sebagai kunci
 * anti-duplikat lewat `dedupe_key`.
 */
class NotificationDispatch extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type', 'dedupe_key', 'trigger', 'summary', 'recipients', 'app_sent',
        'email_sent', 'items', 'status', 'error', 'triggered_by', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'recipients' => 'integer',
        'app_sent' => 'integer',
        'email_sent' => 'integer',
        'items' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
