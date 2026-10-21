<?php

namespace App\Http\Controllers;

use App\Enums\MachineType;
use App\Models\AssetType;
use App\Models\Machine;
use App\Models\NotificationDispatch;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Integration\IntegrationSettings;
use App\Services\Integration\PmReportBuilder;
use App\Services\Notifications\NotificationRecipients;
use App\Services\Notifications\PmAlertDispatcher;
use App\Services\Notifications\PmReportSender;
use App\Services\Notifications\PmScheduleScanner;
use App\Services\SignatureChain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Notifikasi in-app & email.
 *
 * - Kotak Masuk (/notifications, semua user): halaman berdiri sendiri berisi
 *   notifikasi milik akun sendiri, dibuka dari lonceng di header.
 * - Settings → Notifications (Matriks & Jadwal, PIC Perangkat, Kirim &
 *   Riwayat): pemegang `notification.manage` (Admin).
 */
class NotificationController extends Controller
{
    /** Kunci matriks yang boleh diubah dari halaman (form key => setting key). */
    private const MATRIX = [
        'reminder_enabled' => 'notif.reminder.enabled',
        'reminder_app' => 'notif.reminder.app',
        'reminder_email' => 'notif.reminder.email',
        'reminder_to_technician' => 'notif.reminder.to_technician',
        'reminder_to_pic' => 'notif.reminder.to_pic',
        'reminder_to_supervisor' => 'notif.reminder.to_supervisor',
        'overdue_enabled' => 'notif.overdue.enabled',
        'overdue_app' => 'notif.overdue.app',
        'overdue_email' => 'notif.overdue.email',
        'overdue_to_technician' => 'notif.overdue.to_technician',
        'overdue_to_supervisor' => 'notif.overdue.to_supervisor',
        'submitted_app' => 'notif.submitted.app',
        'submitted_email' => 'notif.submitted.email',
        'pic_approved_app' => 'notif.pic_approved.app',
        'pic_approved_email' => 'notif.pic_approved.email',
        'approved_app' => 'notif.approved.app',
        'approved_email' => 'notif.approved.email',
        'revision_app' => 'notif.revision.app',
        'revision_email' => 'notif.revision.email',
        'report_weekly_enabled' => 'report.weekly_enabled',
        'report_monthly_enabled' => 'report.monthly_enabled',
        'report_to_supervisor' => 'report.to_supervisor',
        'report_attach_pdf' => 'report.attach_pdf',
        'report_attach_excel' => 'report.attach_excel',
    ];

    public function __construct(private readonly IntegrationSettings $settings) {}

    /** Kotak Masuk: halaman berdiri sendiri, dibuka dari lonceng di header. */
    public function inbox(Request $request): Response
    {
        $user = $request->user();
        $filter = in_array($request->query('filter'), ['unread', 'schedule', 'overdue', 'approval'], true) ? $request->query('filter') : 'all';

        $inbox = $user->notifications()
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when(in_array($filter, ['schedule', 'overdue', 'approval'], true), fn ($q) => $q->where('data', 'like', '%"category":"'.$filter.'"%'))
            ->paginate(15)
            ->withQueryString()
            ->through(fn (DatabaseNotification $n) => $this->present($n));

        return Inertia::render('Notifications/Inbox', [
            'filter' => $filter,
            'inbox' => $inbox,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /** Settings → Notifications (pengaturan, khusus `notification.manage`). */
    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Notifications', [
            'tab' => in_array($request->query('tab'), ['matrix', 'pic', 'dispatch'], true) ? $request->query('tab') : 'matrix',
            'admin' => $this->adminProps(),
        ]);
    }

    /** @return array<string, mixed> */
    private function adminProps(): array
    {
        $s = $this->settings;
        $ctx = PmScheduleScanner::context();

        $matrix = [];
        foreach (self::MATRIX as $form => $key) {
            $matrix[$form] = $s->bool($key);
        }
        $matrix['reminder_time'] = (string) $s->get('notif.reminder.time');
        $matrix['overdue_times'] = (string) $s->get('notif.overdue.times');
        $matrix['report_weekly_day'] = (int) $s->get('report.weekly_day');
        $matrix['report_weekly_time'] = (string) $s->get('report.weekly_time');
        $matrix['report_monthly_day'] = (int) $s->get('report.monthly_day');
        $matrix['report_monthly_time'] = (string) $s->get('report.monthly_time');
        $matrix['report_recipients'] = (string) $s->get('report.recipients');

        $technicians = NotificationRecipients::technicians();
        $supervisors = NotificationRecipients::supervisors();

        $machines = Machine::with('picUser:id,name')
            ->where('is_active', true)
            ->orderByRaw('type = ? asc', [MachineType::Utility->value])
            ->orderBy('sort_no')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'location', 'type', 'week_group', 'pic_user_id']);
        $fallback = NotificationRecipients::lastPicFromHistory($machines->whereNull('pic_user_id')->pluck('id'));
        $fallbackNames = User::whereIn('id', array_unique(array_values($fallback)))->pluck('name', 'id');

        $person = fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->roles->first()?->name];

        return [
            'matrix' => $matrix,
            'mailReady' => $s->mailReady(),
            'context' => [
                'label' => $ctx['label'],
                'week' => $ctx['week'],
                'start' => $ctx['start'],
                'end' => $ctx['end'],
            ],
            'recipients' => [
                'technicians' => $technicians->map(fn ($t) => $person($t['user']) + ['scopes' => $t['scopes']])->values(),
                'supervisors' => $supervisors->map($person)->values(),
                'reportRecipients' => app(PmReportSender::class)->recipients(),
            ],
            'devices' => $machines->map(fn (Machine $m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'location' => $m->location,
                'type_label' => AssetType::labelFor($m->type),
                'scope' => NotificationRecipients::scopeOf($m->type),
                'week' => $m->week_group,
                'pic_user_id' => $m->pic_user_id,
                'pic_name' => $m->picUser?->name,
                'fallback_pic' => isset($fallback[$m->id]) ? ($fallbackNames[$fallback[$m->id]] ?? null) : null,
            ])->values(),
            'picCandidates' => SignatureChain::picCandidates()->get(['id', 'name', 'email'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values(),
            'dispatches' => NotificationDispatch::with('user:id,name')->latest('created_at')->latest('id')->limit(40)->get()->map(fn ($d) => [
                'id' => $d->id,
                'type' => $d->type,
                'trigger' => $d->trigger,
                'summary' => $d->summary,
                'recipients' => $d->recipients,
                'app_sent' => $d->app_sent,
                'email_sent' => $d->email_sent,
                'status' => $d->status,
                'error' => $d->error,
                'by' => $d->user?->name,
                'at' => $d->created_at?->format('d/m/Y H:i'),
            ]),
        ];
    }

    public function updateMatrix(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (array_keys(self::MATRIX) as $form) {
            $rules[$form] = ['boolean'];
        }

        $data = $request->validate($rules + [
            'reminder_time' => ['required', 'date_format:H:i'],
            'overdue_times' => ['required', 'string', 'max:100', 'regex:/^\s*([01]\d|2[0-3]):[0-5]\d(\s*,\s*([01]\d|2[0-3]):[0-5]\d)*\s*$/'],
            'report_weekly_day' => ['required', 'integer', 'between:1,7'],
            'report_weekly_time' => ['required', 'date_format:H:i'],
            'report_monthly_day' => ['required', 'integer', 'between:1,28'],
            'report_monthly_time' => ['required', 'date_format:H:i'],
            'report_recipients' => ['nullable', 'string', 'max:2000'],
        ], [
            'overdue_times.regex' => 'Format jam: HH:MM dipisah koma, mis. 07:00, 14:00.',
        ]);

        $values = [];
        foreach (self::MATRIX as $form => $key) {
            $values[$key] = $request->boolean($form);
        }

        $times = collect(explode(',', $data['overdue_times']))->map(fn ($t) => trim($t))->filter()->unique()->sort()->values();

        $values += [
            'notif.reminder.time' => $data['reminder_time'],
            'notif.overdue.times' => $times->implode(','),
            'report.weekly_day' => (int) $data['report_weekly_day'],
            'report.weekly_time' => $data['report_weekly_time'],
            'report.monthly_day' => (int) $data['report_monthly_day'],
            'report.monthly_time' => $data['report_monthly_time'],
            'report.recipients' => implode(', ', IntegrationSettings::parseRecipients((string) ($data['report_recipients'] ?? ''))),
        ];

        $this->settings->set($values);

        AuditLogService::record('notification.settings.update', null, null, collect($values)->except('report.recipients')->all());

        return back()->with('success', 'Pengaturan notifikasi tersimpan.');
    }

    public function assignPic(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'machine_ids' => ['required', 'array', 'min:1'],
            'machine_ids.*' => ['integer', 'exists:assets,id'],
            'pic_user_id' => ['nullable', 'integer', Rule::in(SignatureChain::picCandidates()->pluck('id')->all())],
        ], [
            'pic_user_id.in' => 'Pilih User PIC yang valid (role User).',
            'machine_ids.required' => 'Pilih minimal satu perangkat.',
        ]);

        $count = Machine::whereIn('id', $data['machine_ids'])->update(['pic_user_id' => $data['pic_user_id'] ?? null]);
        $name = isset($data['pic_user_id']) ? User::find($data['pic_user_id'])?->name : null;

        AuditLogService::record('notification.pic.assign', null, null, [
            'machine_ids' => $data['machine_ids'],
            'pic_user_id' => $data['pic_user_id'] ?? null,
            'pic_name' => $name,
        ]);

        return back()->with('success', $name
            ? "{$count} perangkat kini menjadi tanggung jawab {$name}."
            : "PIC {$count} perangkat dikosongkan (memakai PIC dari riwayat PM).");
    }

    /** Pratinjau penerima reminder / keterlambatan tanpa mengirim. */
    public function preview(Request $request, PmAlertDispatcher $dispatcher): JsonResponse
    {
        $type = $request->validate(['type' => ['required', Rule::in(['reminder', 'overdue'])]])['type'];

        $result = $type === 'reminder'
            ? $dispatcher->reminder(force: true, dryRun: true)
            : $dispatcher->overdue(force: true, dryRun: true);

        return response()->json($result);
    }

    public function send(Request $request, PmAlertDispatcher $dispatcher, PmReportSender $reports): RedirectResponse
    {
        $type = $request->validate(['type' => ['required', Rule::in(['reminder', 'overdue', 'weekly', 'monthly'])]])['type'];
        $user = $request->user();

        if (in_array($type, ['weekly', 'monthly'], true)) {
            try {
                $to = $reports->send($type === 'monthly' ? PmReportBuilder::MONTHLY : PmReportBuilder::WEEKLY, null, $user);
            } catch (Throwable $e) {
                report($e);

                return back()->with('error', 'Laporan gagal dikirim: '.$e->getMessage());
            }

            return back()->with('success', 'Laporan '.($type === 'monthly' ? 'bulanan' : 'mingguan').' dikirim ke '.implode(', ', $to).'.');
        }

        $result = $type === 'reminder'
            ? $dispatcher->reminder(force: true, by: $user)
            : $dispatcher->overdue(force: true, by: $user);

        return back()->with($result['status'] === 'sent' ? 'success' : 'error', $result['message']);
    }

    // ------------------------------------------------------------------
    // Kotak masuk (semua user)
    // ------------------------------------------------------------------

    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->query('only') === 'count') {
            return response()->json(['unread' => $user->unreadNotifications()->count()]);
        }

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit(8)->get()->map(fn ($n) => $this->present($n, compact: true)),
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');

        // Hanya path internal, supaya data notifikasi tidak bisa jadi open redirect.
        return str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : redirect()->route('notifications.inbox');
    }

    public function markRead(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return $request->wantsJson() ? response()->json(['ok' => true]) : back();
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->wantsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return back();
    }

    public function clearRead(Request $request): RedirectResponse
    {
        $count = $request->user()->readNotifications()->delete();

        return back()->with('success', "{$count} notifikasi yang sudah dibaca dihapus.");
    }

    /** @return array<string, mixed> */
    private function present(DatabaseNotification $n, bool $compact = false): array
    {
        $data = $n->data;

        $out = [
            'id' => $n->id,
            'category' => $data['category'] ?? 'other',
            'kind' => $data['kind'] ?? null,
            'title' => $data['title'] ?? 'Notifikasi',
            'body' => $data['body'] ?? '',
            'read' => $n->read_at !== null,
            'at' => $n->created_at?->format('d/m/Y H:i'),
            'ago' => $n->created_at?->locale('id')->diffForHumans(),
            'open_url' => route('notifications.open', $n->id, false),
        ];

        if ($compact) {
            return $out;
        }

        return $out + [
            'doc_number' => $data['doc_number'] ?? null,
            'unit' => $data['unit'] ?? null,
            'period' => $data['period'] ?? null,
            'actor' => $data['actor'] ?? null,
            'actor_role' => $data['actor_role'] ?? null,
            'acted_at' => $data['acted_at'] ?? null,
            'reason' => $data['reason'] ?? null,
            'roles' => $data['roles'] ?? [],
            'total' => $data['total'] ?? null,
            'items' => $data['items'] ?? [],
        ];
    }
}
