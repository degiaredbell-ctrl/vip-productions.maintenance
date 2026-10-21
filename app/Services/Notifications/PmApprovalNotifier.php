<?php

namespace App\Services\Notifications;

use App\Enums\PmStatus;
use App\Enums\SignatureStage;
use App\Models\NotificationDispatch;
use App\Models\PmRecord;
use App\Models\User;
use App\Notifications\PmApprovalNotification;
use App\Services\Integration\IntegrationSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Notifikasi dokumen approval (matriks no. 3). Tidak pernah menggagalkan
 * proses utama (tanda tangan / revisi) bila pengiriman bermasalah.
 */
class PmApprovalNotifier
{
    public function __construct(private readonly IntegrationSettings $settings) {}

    /** Setelah satu tahap ditandatangani. */
    public function afterSign(PmRecord $record, SignatureStage $signed): void
    {
        $record->refresh()->load('machine', 'picUser', 'technician', 'signatures');
        $signature = $record->signatureFor($signed);
        $actor = $signature?->signed_by_name ?: Auth::user()?->name;
        $at = ($signature?->signed_at ?? now())->format('d/m/Y H:i');

        match (true) {
            $signed === SignatureStage::Technician && $record->status === PmStatus::Submitted => $this->notify(
                $record, PmApprovalNotification::SUBMITTED, $this->excludeTechnician($record, NotificationRecipients::picForRecord($record)), $actor, 'Teknisi', $at,
            ),
            $signed === SignatureStage::Pic && $record->status === PmStatus::PicApproved => $this->notify(
                $record, PmApprovalNotification::PIC_APPROVED, $this->excludeTechnician($record, NotificationRecipients::supervisors()), $actor, 'User PIC', $at,
            ),
            $signed === SignatureStage::Supervisor && $record->status === PmStatus::Approved => $this->notify(
                $record, PmApprovalNotification::APPROVED, collect([$record->technician])->filter(), $actor, 'Atasan', $at,
            ),
            default => null,
        };
    }

    /** Dokumen dikembalikan untuk revisi oleh User PIC / Atasan (3.4). */
    public function revision(PmRecord $record, SignatureStage $stage, string $reason, ?User $by): void
    {
        $record->loadMissing('machine', 'technician');

        $this->notify(
            $record,
            PmApprovalNotification::REVISION,
            collect([$record->technician])->filter(),
            $by?->name,
            $stage->shortLabel(),
            now()->format('d/m/Y H:i'),
            $reason,
        );
    }

    /** @param  Collection<int, User>  $users */
    private function notify(PmRecord $record, string $event, Collection $users, ?string $actor, ?string $role, string $at, ?string $reason = null): void
    {
        $app = $this->settings->bool("notif.{$event}.app");
        $email = $this->settings->bool("notif.{$event}.email");
        $users = $users->filter()->unique('id')->values();

        if ((! $app && ! $email) || $users->isEmpty()) {
            return;
        }

        $appSent = 0;
        $emailSent = 0;

        try {
            foreach ($users as $user) {
                $channels = NotificationChannels::resolve($user, $app, $email);
                if ($channels === []) {
                    continue;
                }

                Notification::send($user, new PmApprovalNotification($record, $event, $channels, $actor, $role, $at, $reason));
                $appSent += in_array('database', $channels, true) ? 1 : 0;
                $emailSent += in_array('mail', $channels, true) ? 1 : 0;
            }

            NotificationDispatch::create([
                'type' => "approval.{$event}",
                'trigger' => 'event',
                'summary' => $record->documentNumber().' · '.($record->machine?->name ?? '-').' → '.$users->pluck('name')->join(', '),
                'recipients' => $users->count(),
                'app_sent' => $appSent,
                'email_sent' => $emailSent,
                'items' => 1,
                'triggered_by' => Auth::id(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Gagal mengirim notifikasi approval PM: '.$e->getMessage());
        }
    }

    /**
     * Pembuat checklist tidak pernah dikirimi untuk menyetujui miliknya sendiri.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private function excludeTechnician(PmRecord $record, Collection $users): Collection
    {
        return $users->reject(fn (User $u) => $record->technician_id !== null && $u->id === $record->technician_id)->values();
    }
}
