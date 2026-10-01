<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public static function record(string $action, ?string $subjectType = null, ?int $subjectId = null, ?array $changes = null): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes' => $changes,
            'ip' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
