<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\ClientInfo;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public static function record(string $action, ?string $subjectType = null, ?int $subjectId = null, ?array $changes = null): void
    {
        // IP asli klien (menghormati proxy tepercaya) + perangkat; kosong
        // untuk aksi otomatis dari antrean/penjadwal. Lihat ClientInfo.
        $client = ClientInfo::capture();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes' => $changes,
            'ip' => $client['ip'],
            'user_agent' => $client['user_agent'],
            'created_at' => now(),
        ]);
    }
}
