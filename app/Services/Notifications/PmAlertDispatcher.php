<?php

namespace App\Services\Notifications;

use App\Models\NotificationDispatch;
use App\Models\User;
use App\Notifications\PmScheduleDigestNotification;
use App\Services\Integration\IntegrationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Notifikasi terjadwal (matriks no. 1 & 2). Setiap penerima mendapat SATU
 * notifikasi berisi daftar perangkat sesuai scope-nya:
 *
 * - Teknisi : perangkat sesuai jenisnya (mesin / utility).
 * - User/PIC: perangkat yang menjadi tanggung jawabnya (hanya reminder).
 * - Atasan  : semua perangkat.
 *
 * User yang memegang beberapa peran mendapat gabungan daftarnya, bukan
 * notifikasi ganda.
 */
class PmAlertDispatcher
{
    public const REMINDER_WEEK = 2;

    public function __construct(private readonly IntegrationSettings $settings) {}

    /**
     * Reminder saat periode berjalan memasuki Minggu ke-2. Dikirim sekali per
     * periode (kunci `reminder:{tahun}:{periode}`); bila server mati tepat di
     * hari pertama Minggu ke-2, terkirim pada jadwal berikutnya selama masih
     * Minggu ke-2.
     *
     * @return array<string, mixed>
     */
    public function reminder(bool $force = false, bool $dryRun = false, ?User $by = null): array
    {
        $ctx = PmScheduleScanner::context();
        $type = 'reminder';

        if (! $force && ! $this->settings->bool('notif.reminder.enabled')) {
            return $this->skip($type, 'Reminder Minggu ke-2 dinonaktifkan.', $dryRun);
        }

        if (! $force && $ctx['week'] !== self::REMINDER_WEEK) {
            return $this->skip($type, "Periode {$ctx['label']} sedang Minggu ke-{$ctx['week']}, bukan Minggu ke-".self::REMINDER_WEEK.'.', $dryRun, log: false);
        }

        $dedupe = $force ? null : "reminder:{$ctx['year']}:{$ctx['period']->value}";
        if ($dedupe && NotificationDispatch::where('dedupe_key', $dedupe)->exists()) {
            return $this->skip($type, "Reminder periode {$ctx['label']} sudah pernah dikirim.", $dryRun, log: false);
        }

        $items = PmScheduleScanner::pending($ctx['period'], $ctx['year'])
            ->map(fn ($m) => PmScheduleScanner::item($m, $ctx['period'], $ctx['year']));

        if ($items->isEmpty()) {
            return $this->skip($type, "Semua perangkat periode {$ctx['label']} sudah di-PM.", $dryRun);
        }

        $targets = $this->targets(
            $items,
            technician: $this->settings->bool('notif.reminder.to_technician'),
            pic: $this->settings->bool('notif.reminder.to_pic'),
            supervisor: $this->settings->bool('notif.reminder.to_supervisor'),
        );

        // Konteks memakai minggu sebenarnya; saat dipaksa di luar Minggu ke-2
        // isi notifikasi tetap jujur tentang minggu berjalan.
        $context = ['label' => $ctx['label'], 'week' => $ctx['week'] ?? self::REMINDER_WEEK, 'start' => $ctx['start'], 'end' => $ctx['end']];

        return $this->deliver($type, PmScheduleDigestNotification::REMINDER, $items, $targets, $context, $dedupe, $dryRun, $by);
    }

    /**
     * Keterlambatan PM. Dijadwalkan setiap hari pada jam di pengaturan
     * (default 07:00 & 14:00 WIB); daftar dihitung ulang setiap kali, jadi
     * perangkat yang PM-nya sudah dikerjakan otomatis tidak disebut lagi dan
     * pengiriman berhenti bila tidak ada lagi yang terlambat.
     *
     * @return array<string, mixed>
     */
    public function overdue(bool $force = false, bool $dryRun = false, ?User $by = null): array
    {
        $ctx = PmScheduleScanner::context();
        $type = 'overdue';

        if (! $force && ! $this->settings->bool('notif.overdue.enabled')) {
            return $this->skip($type, 'Notifikasi keterlambatan dinonaktifkan.', $dryRun);
        }

        // Satu kiriman per slot jam, supaya scheduler yang berjalan ganda
        // (mis. restart tepat di menit yang sama) tidak mengirim dua kali.
        $dedupe = $force ? null : 'overdue:'.CarbonImmutable::now()->format('Y-m-d:H');
        if ($dedupe && NotificationDispatch::where('dedupe_key', $dedupe)->exists()) {
            return $this->skip($type, 'Notifikasi keterlambatan jam ini sudah dikirim.', $dryRun, log: false);
        }

        $items = PmScheduleScanner::overdue($ctx['period'], $ctx['year'])
            ->map(fn ($m) => PmScheduleScanner::item($m, $ctx['period'], $ctx['year']));

        if ($items->isEmpty()) {
            return $this->skip($type, "Tidak ada perangkat terlambat pada periode {$ctx['label']}.", $dryRun);
        }

        $targets = $this->targets(
            $items,
            technician: $this->settings->bool('notif.overdue.to_technician'),
            pic: false,
            supervisor: $this->settings->bool('notif.overdue.to_supervisor'),
        );

        $context = ['label' => $ctx['label'], 'week' => $ctx['week'], 'start' => $ctx['start'], 'end' => $ctx['end']];

        return $this->deliver($type, PmScheduleDigestNotification::OVERDUE, $items, $targets, $context, $dedupe, $dryRun, $by);
    }

    /**
     * Penerima beserta perangkat masing-masing.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array{user: User, roles: list<string>, items: list<array<string, mixed>>}>
     */
    public function targets(Collection $items, bool $technician, bool $pic, bool $supervisor): Collection
    {
        /** @var array<int, array{user: User, roles: list<string>, ids: array<int, true>}> $map */
        $map = [];
        $add = function (User $user, string $role, iterable $ids) use (&$map) {
            $map[$user->id] ??= ['user' => $user, 'roles' => [], 'ids' => []];
            if (! in_array($role, $map[$user->id]['roles'], true)) {
                $map[$user->id]['roles'][] = $role;
            }
            foreach ($ids as $id) {
                $map[$user->id]['ids'][$id] = true;
            }
        };

        if ($technician) {
            foreach (NotificationRecipients::technicians() as $row) {
                $ids = $items->whereIn('scope', $row['scopes'])->pluck('id');
                $label = count($row['scopes']) > 1 ? 'Teknisi'
                    : ($row['scopes'][0] === NotificationRecipients::SCOPE_UTILITY ? 'Teknisi Utility' : 'Teknisi Mesin');
                $add($row['user'], $label, $ids);
            }
        }

        if ($pic) {
            $picMap = NotificationRecipients::picMap($items->pluck('id'));
            $users = User::whereIn('id', array_unique(array_values($picMap)))->get()->keyBy('id');
            foreach ($picMap as $machineId => $userId) {
                if ($user = $users->get($userId)) {
                    $add($user, 'User PIC', [$machineId]);
                }
            }
        }

        if ($supervisor) {
            foreach (NotificationRecipients::supervisors() as $user) {
                $add($user, 'Atasan', $items->pluck('id'));
            }
        }

        return collect($map)
            ->map(fn ($row) => [
                'user' => $row['user'],
                'roles' => $row['roles'],
                // Urutan daftar mengikuti urutan $items (minggu / hari terlambat).
                'items' => $items->filter(fn ($i) => isset($row['ids'][$i['id']]))->values()->all(),
            ])
            ->filter(fn ($row) => $row['items'] !== [])
            ->sortBy(fn ($row) => $row['user']->name)
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  Collection<int, array{user: User, roles: list<string>, items: list<array<string, mixed>>}>  $targets
     * @return array<string, mixed>
     */
    private function deliver(string $type, string $kind, Collection $items, Collection $targets, array $context, ?string $dedupe, bool $dryRun, ?User $by): array
    {
        $app = $this->settings->bool("notif.{$type}.app");
        $email = $this->settings->bool("notif.{$type}.email");

        $preview = $targets->map(function ($t) use ($app, $email) {
            $channels = NotificationChannels::resolve($t['user'], $app, $email);

            return [
                'id' => $t['user']->id,
                'name' => $t['user']->name,
                'email' => $t['user']->email,
                'roles' => $t['roles'],
                'count' => count($t['items']),
                'channels' => $channels,
            ];
        })->values();

        $label = $type === 'overdue' ? 'Keterlambatan PM' : 'Reminder Minggu ke-2';
        $summary = "{$label} · {$context['label']} · {$items->count()} perangkat → {$targets->count()} penerima";

        if ($dryRun) {
            return ['status' => 'preview', 'message' => $summary, 'items' => $items->count(), 'recipients' => $preview->all()];
        }

        if (! $app && ! $email) {
            return $this->skip($type, "{$label}: saluran Apps dan Email sama-sama nonaktif.", false);
        }

        if ($targets->isEmpty()) {
            return $this->skip($type, "{$label}: tidak ada penerima untuk {$items->count()} perangkat.", false);
        }

        // Klaim kunci dulu supaya dua proses tidak mengirim bersamaan.
        try {
            $log = NotificationDispatch::create([
                'type' => $type,
                'dedupe_key' => $dedupe,
                'trigger' => $by ? 'manual' : 'schedule',
                'summary' => mb_substr($summary, 0, 500),
                'items' => $items->count(),
                'recipients' => $targets->count(),
                'status' => 'sending',
                'triggered_by' => $by?->id,
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            return $this->skip($type, "{$label} sudah dikirim oleh proses lain.", false, log: false);
        }

        $appSent = 0;
        $emailSent = 0;
        $errors = [];

        foreach ($targets as $i => $t) {
            $channels = $preview[$i]['channels'];
            if ($channels === []) {
                continue;
            }

            try {
                Notification::send($t['user'], new PmScheduleDigestNotification($kind, $t['items'], $context, $channels, $t['roles']));
                $appSent += in_array('database', $channels, true) ? 1 : 0;
                $emailSent += in_array('mail', $channels, true) ? 1 : 0;
            } catch (Throwable $e) {
                $errors[] = "{$t['user']->name}: {$e->getMessage()}";
            }
        }

        $log->update([
            'app_sent' => $appSent,
            'email_sent' => $emailSent,
            'status' => $errors && ! $appSent && ! $emailSent ? 'failed' : 'sent',
            'error' => $errors ? mb_substr(implode("\n", $errors), 0, 2000) : null,
        ]);

        return [
            'status' => 'sent',
            'message' => "{$summary} (Apps: {$appSent}, Email: {$emailSent})",
            'items' => $items->count(),
            'recipients' => $preview->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function skip(string $type, string $message, bool $dryRun, bool $log = true): array
    {
        if ($log && ! $dryRun) {
            NotificationDispatch::create([
                'type' => $type,
                'trigger' => 'schedule',
                'summary' => mb_substr($message, 0, 500),
                'status' => 'skipped',
                'created_at' => now(),
            ]);
        }

        return ['status' => 'skipped', 'message' => $message, 'items' => 0, 'recipients' => []];
    }
}
