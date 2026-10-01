<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::with('user')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user_name' => $log->user?->name ?? 'System',
                    'subject_type' => $log->subject_type,
                    'subject_id' => $log->subject_id,
                    'ip' => $log->ip,
                    'created_at' => $log->created_at->format('d M Y H:i'),
                ];
            });

        return Inertia::render('Admin/AuditLogs', [
            'logs' => $logs,
        ]);
    }
}
