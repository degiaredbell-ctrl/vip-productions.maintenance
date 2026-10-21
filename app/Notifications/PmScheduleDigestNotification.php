<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi terjadwal berisi DAFTAR perangkat, dibuat per penerima sesuai
 * scope-nya (lihat PmAlertDispatcher):
 *
 * - `reminder` : periode berjalan memasuki Minggu ke-2 → perangkat yang harus di-PM.
 * - `overdue`  : perangkat yang minggu jadwalnya sudah lewat tetapi belum di-PM.
 */
class PmScheduleDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const REMINDER = 'reminder';
    public const OVERDUE = 'overdue';

    /** Batas item yang disimpan di kotak masuk (email memuat daftar lengkap). */
    public const APP_ITEM_LIMIT = 60;

    public int $tries = 3;

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array{label: string, week: ?int, start: string, end: string}  $context
     * @param  list<string>  $channels
     * @param  list<string>  $roles  Label peran penerima (Teknisi Mesin, Atasan, …)
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $items,
        public readonly array $context,
        public readonly array $channels,
        public readonly array $roles = [],
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function title(): string
    {
        $count = count($this->items);

        return $this->kind === self::OVERDUE
            ? "Keterlambatan PM: {$count} perangkat belum di-PM"
            : "Periode {$this->context['label']} memasuki Minggu ke-{$this->context['week']}";
    }

    public function body(): string
    {
        $count = count($this->items);

        return $this->kind === self::OVERDUE
            ? "Periode {$this->context['label']}".($this->context['week'] ? " · Minggu ke-{$this->context['week']}" : '').". {$count} perangkat sudah melewati minggu jadwalnya dan belum di-PM."
            : "Periode {$this->context['label']} ({$this->context['start']} – {$this->context['end']}) sedang berjalan di Minggu ke-{$this->context['week']}. {$count} perangkat harus di-PM.";
    }

    private function listPath(): string
    {
        return route('dashboard', $this->kind === self::OVERDUE ? ['status' => 'overdue'] : [], false);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->kind === self::OVERDUE ? 'overdue' : 'schedule',
            'kind' => $this->kind,
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->listPath(),
            'period' => $this->context['label'],
            'week' => $this->context['week'],
            'roles' => $this->roles,
            'total' => count($this->items),
            'items' => array_slice($this->items, 0, self::APP_ITEM_LIMIT),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $overdue = $this->kind === self::OVERDUE;
        $base = rtrim((string) config('app.url'), '/');

        return (new MailMessage)
            ->level($overdue ? 'error' : 'default')
            ->subject(($overdue ? '[Terlambat] ' : '[Jadwal PM] ').$this->title())
            ->markdown('mail.pm-digest', [
                'name' => $notifiable->name ?? '',
                'overdue' => $overdue,
                'title' => $this->title(),
                'body' => $this->body(),
                'roles' => $this->roles,
                'items' => $this->items,
                'base' => $base,
                'url' => $base.$this->listPath(),
                'generatedAt' => now()->format('d/m/Y H:i'),
            ]);
    }
}
