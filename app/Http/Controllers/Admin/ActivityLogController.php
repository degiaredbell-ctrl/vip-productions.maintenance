<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\ClientInfo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman "Activity Logs" (dulu "Audit Log").
 *
 * Sumber datanya tetap tabel `audit_logs` yang diisi lewat AuditLogService —
 * tidak ada sistem log kedua. Yang ditambahkan di sini adalah pencarian,
 * filter (aksi, pelaku, rentang tanggal), paginasi, dan detail perubahan
 * sehingga catatan bisa ditelusuri, bukan sekadar dibaca 100 baris terakhir.
 */
class ActivityLogController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'action' => (string) $request->query('action', ''),
            'user_id' => (string) $request->query('user_id', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];

        $logs = AuditLog::query()
            ->with('user')
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $needle = '%'.$filters['q'].'%';
                $query->where(function ($inner) use ($needle) {
                    $inner->where('action', 'like', $needle)
                        ->orWhere('subject_type', 'like', $needle)
                        ->orWhere('ip', 'like', $needle)
                        ->orWhere('user_agent', 'like', $needle)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $needle));
                });
            })
            ->when($filters['action'] !== '', fn ($query) => $query->where('action', $filters['action']))
            ->when($filters['user_id'] !== '', fn ($query) => $query->where('user_id', (int) $filters['user_id']))
            ->when($filters['from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $logs->through(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'action_group' => str_contains($log->action, '.') ? strstr($log->action, '.', true) : $log->action,
            'user_id' => $log->user_id,
            'user_name' => $log->user?->name ?? 'System',
            'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
            'subject_id' => $log->subject_id,
            'ip' => $log->ip,
            // Keterangan jaringan (mis. gateway NAT/VPN, internal server) dan
            // perangkat, karena satu IP bisa dipakai banyak perangkat.
            'ip_label' => ClientInfo::describeIp($log->ip),
            'device' => ClientInfo::device($log->user_agent),
            'user_agent' => $log->user_agent,
            'is_system' => $log->ip === null && $log->user_agent === null,
            'changes' => $log->changes,
            'created_at' => $log->created_at?->format('d M Y H:i'),
            'created_at_human' => $log->created_at?->diffForHumans(),
        ]);

        return Inertia::render('Admin/ActivityLogs', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'users' => User::query()
                ->select(['id', 'name'])
                ->whereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id'))
                ->orderBy('name')
                ->get(),
            'stats' => [
                'total' => AuditLog::query()->count(),
                'today' => AuditLog::query()->whereDate('created_at', today())->count(),
                'actors' => AuditLog::query()->whereNotNull('user_id')->distinct()->count('user_id'),
            ],
        ]);
    }
}
