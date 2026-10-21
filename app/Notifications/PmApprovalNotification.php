<?php

namespace App\Notifications;

use App\Models\PmRecord;
use App\Services\Notifications\PmScheduleScanner;
use App\Enums\Period;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi dokumen approval PM (real-time, dipicu aksi user):
 *
 * - 3.1 `submitted`    Teknisi selesai Create PM      → User/PIC
 * - 3.2 `pic_approved` User/PIC selesai Approve PM    → Atasan
 * - 3.3 `approved`     Atasan selesai Approve PM      → Teknisi
 * - 3.4 `revision`     Dokumen direvisi User/Atasan   → Teknisi (+ alasan)
 *
 * Isi selalu memuat nomor dokumen, nama perangkat, pengirim aksi, waktu aksi,
 * dan link langsung ke dokumen. Dikirim lewat antrean setelah transaksi selesai.
 */
class PmApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const SUBMITTED = 'submitted';
    public const PIC_APPROVED = 'pic_approved';
    public const APPROVED = 'approved';
    public const REVISION = 'revision';

    public int $tries = 3;

    /** @param  list<string>  $channels */
    public function __construct(
        public readonly PmRecord $record,
        public readonly string $event,
        public readonly array $channels,
        public readonly ?string $actor = null,
        public readonly ?string $actorRole = null,
        public readonly ?string $actedAt = null,
        public readonly ?string $reason = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{doc: string, unit: string, period: string, location: ?string} */
    private function facts(): array
    {
        $record = $this->record->loadMissing('machine');
        $machine = $record->machine;

        return [
            'doc' => $record->documentNumber(),
            'unit' => trim(($machine?->code ? $machine->code.' — ' : '').($machine?->name ?? 'Perangkat')),
            'period' => ($p = Period::tryFrom($record->period)) ? PmScheduleScanner::periodLabel($p, $record->year) : "{$record->period} {$record->year}",
            'location' => $machine?->location,
        ];
    }

    private function path(): string
    {
        $machine = $this->record->machine;

        if (! $machine) {
            return route('approvals.index', [], false);
        }

        $forTechnician = in_array($this->event, [self::APPROVED, self::REVISION], true);

        return route('machines.pm.create', [
            'machine' => $machine->id,
            'year' => $this->record->year,
            'period' => $this->record->period,
            'return' => 'approvals',
            'tab' => $forTechnician ? 'mine' : 'queue',
        ], false);
    }

    public function title(): string
    {
        $f = $this->facts();

        return match ($this->event) {
            self::SUBMITTED => "PM menunggu persetujuan Anda: {$f['unit']}",
            self::PIC_APPROVED => "PM disetujui User PIC, menunggu Atasan: {$f['unit']}",
            self::APPROVED => "PM selesai disetujui Atasan: {$f['unit']}",
            default => "PM perlu revisi: {$f['unit']}",
        };
    }

    public function body(): string
    {
        $f = $this->facts();
        $by = $this->actor ?: '-';

        return match ($this->event) {
            self::SUBMITTED => "Dokumen {$f['doc']} ({$f['period']}) dibuat oleh {$by} dan menunggu persetujuan User PIC.",
            self::PIC_APPROVED => "Dokumen {$f['doc']} ({$f['period']}) sudah disetujui {$by} (User PIC) dan menunggu Persetujuan Atasan.",
            self::APPROVED => "Dokumen {$f['doc']} ({$f['period']}) sudah disetujui {$by} (Atasan). Semua tahap selesai.",
            default => "Dokumen {$f['doc']} ({$f['period']}) dikembalikan untuk revisi oleh {$by}".($this->actorRole ? " ({$this->actorRole})" : '').'. Alasan: '.($this->reason ?: '-'),
        };
    }

    public function toArray(object $notifiable): array
    {
        $f = $this->facts();

        return [
            'category' => 'approval',
            'kind' => $this->event,
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->path(),
            'record_id' => $this->record->id,
            'doc_number' => $f['doc'],
            'unit' => $f['unit'],
            'period' => $f['period'],
            'actor' => $this->actor,
            'actor_role' => $this->actorRole,
            'acted_at' => $this->actedAt,
            'reason' => $this->reason,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $f = $this->facts();
        $url = rtrim((string) config('app.url'), '/').$this->path();

        $message = (new MailMessage)
            ->subject("[{$f['doc']}] ".$this->title())
            ->greeting('Halo, '.($notifiable->name ?? '').'!')
            ->line($this->body())
            ->line("**No. Dokumen:** {$f['doc']}")
            ->line("**Perangkat:** {$f['unit']}".($f['location'] ? " · {$f['location']}" : ''))
            ->line("**Periode:** {$f['period']}")
            ->line('**Oleh:** '.($this->actor ?: '-').($this->actorRole ? " ({$this->actorRole})" : ''))
            ->line('**Waktu:** '.($this->actedAt ?: now()->format('d/m/Y H:i')).' WIB');

        return match ($this->event) {
            self::SUBMITTED, self::PIC_APPROVED => $message->action('Buka & Tinjau Dokumen', $url),
            self::APPROVED => $message->level('success')->action('Lihat Dokumen', $url),
            default => $message->level('error')
                ->line('**Diminta oleh:** '.($this->actor ?: '-').($this->actorRole ? " ({$this->actorRole})" : ''))
                ->line('**Catatan revisi:** '.($this->reason ?: '-'))
                ->action('Revisi Dokumen', $url),
        };
    }
}
