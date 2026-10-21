<?php

namespace App\Notifications;

use App\Enums\Period;
use App\Models\PmRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi email perjalanan PM di rantai persetujuan:
 *
 * - `awaiting` → ke approver tahap berikutnya (User PIC / Atasan).
 * - `rejected` → ke teknisi pembuat, beserta alasan.
 * - `approved` → ke teknisi pembuat saat Atasan menyetujui (selesai).
 *
 * Lewat antrean dan baru dikirim setelah transaksi database selesai, supaya
 * email tidak terkirim untuk perubahan yang ternyata dibatalkan.
 */
class PmStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const AWAITING = 'awaiting';
    public const REJECTED = 'rejected';
    public const APPROVED = 'approved';

    public int $tries = 3;

    public function __construct(
        public readonly PmRecord $record,
        public readonly string $event,
        public readonly ?string $stageLabel = null,
        public readonly ?string $actor = null,
        public readonly ?string $reason = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $record = $this->record->loadMissing('machine');
        $machine = $record->machine;
        $unit = trim(($machine?->code ? $machine->code.' — ' : '').($machine?->name ?? 'Unit'));
        $period = (Period::tryFrom($record->period)?->label() ?? $record->period).' '.$record->year;

        $url = $machine ? route('machines.pm.create', [
            'machine' => $machine->id,
            'year' => $record->year,
            'period' => $record->period,
            'return' => 'approvals',
            'tab' => $this->event === self::AWAITING ? 'queue' : 'mine',
        ]) : route('approvals.index');

        $message = (new MailMessage)->greeting('Halo, '.($notifiable->name ?? '').'!');

        return match ($this->event) {
            self::AWAITING => $message
                ->subject("Menunggu persetujuan Anda: PM {$unit} ({$period})")
                ->line("Checklist PM **{$unit}** periode **{$period}** menunggu **{$this->stageLabel}** dari Anda.")
                ->line('Teknisi: '.($record->technicianName() ?: '-'))
                ->action('Buka & Tinjau Checklist', $url)
                ->line('Anda juga bisa melihat semua antrean di menu Persetujuan.'),

            self::REJECTED => $message
                ->level('error')
                ->subject("PM ditolak, perlu revisi: {$unit} ({$period})")
                ->line("Checklist PM **{$unit}** periode **{$period}** ditolak".($this->actor ? " oleh **{$this->actor}**" : '').' dan dikembalikan untuk revisi.')
                ->line('Alasan: '.($this->reason ?: '-'))
                ->action('Revisi Checklist', $url),

            default => $message
                ->level('success')
                ->subject("PM selesai disetujui: {$unit} ({$period})")
                ->line("Checklist PM **{$unit}** periode **{$period}** sudah disetujui".($this->actor ? " oleh **{$this->actor}**" : '').'. Semua tahap persetujuan selesai.')
                ->action('Lihat Checklist', $url),
        };
    }
}
