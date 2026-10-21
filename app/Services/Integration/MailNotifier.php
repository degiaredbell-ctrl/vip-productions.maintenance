<?php

namespace App\Services\Integration;

use App\Enums\PmStatus;
use App\Enums\Role;
use App\Enums\SignatureStage;
use App\Models\PmRecord;
use App\Models\SparePart;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Notifications\PmStatusNotification;
use App\Services\SignatureChain;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Titik tunggal pengiriman notifikasi email. Setiap metode memeriksa saklar
 * di Settings → Integrate dan tidak pernah menggagalkan proses utama (tanda
 * tangan, penolakan, transaksi stok) bila email bermasalah.
 */
class MailNotifier
{
    public function __construct(private readonly IntegrationSettings $settings) {}

    /** Setelah sebuah tahap ditandatangani: beri tahu tahap berikutnya / pembuat. */
    public function afterSign(PmRecord $record, SignatureStage $signed): void
    {
        $record->refresh();

        if ($record->status === PmStatus::Approved) {
            $this->pmApproved($record);

            return;
        }

        $next = $record->status->awaiting();
        if ($next !== null && $next !== SignatureStage::Technician) {
            $this->pmAwaiting($record, $next);
        }
    }

    public function pmAwaiting(PmRecord $record, SignatureStage $stage): void
    {
        if (! $this->settings->featureOn('notify.pm_awaiting')) {
            return;
        }

        $this->send(
            $this->approversFor($record, $stage),
            new PmStatusNotification($record, PmStatusNotification::AWAITING, $stage->label()),
        );
    }

    public function pmRejected(PmRecord $record, string $reason, ?string $by): void
    {
        if (! $this->settings->featureOn('notify.pm_rejected')) {
            return;
        }

        $this->send(
            collect([$record->technician])->filter(),
            new PmStatusNotification($record, PmStatusNotification::REJECTED, actor: $by, reason: $reason),
        );
    }

    public function pmApproved(PmRecord $record): void
    {
        if (! $this->settings->featureOn('notify.pm_approved')) {
            return;
        }

        $this->send(
            collect([$record->technician])->filter(),
            new PmStatusNotification($record, PmStatusNotification::APPROVED, actor: $record->approver?->name),
        );
    }

    public function lowStock(SparePart $part): void
    {
        if (! $this->settings->featureOn('notify.low_stock')) {
            return;
        }

        $recipients = $this->settings->recipients('notify.low_stock_recipients')
            ?: $this->settings->recipients('report.recipients');

        if ($recipients === []) {
            return;
        }

        try {
            Notification::route('mail', $recipients)->notify(new LowStockNotification($part));
        } catch (Throwable $e) {
            Log::warning('Gagal mengantrekan email stok: '.$e->getMessage());
        }
    }

    /**
     * Penerima email "menunggu persetujuan".
     *
     * - Tahap User PIC: PIC yang ditunjuk teknisi; record lama tanpa PIC
     *   dikirim ke semua User PIC.
     * - Tahap Atasan: pemegang izin tahap Atasan selain admin; admin hanya
     *   dikirimi bila tidak ada orang lain.
     *
     * Pembuat checklist tidak pernah dikirimi untuk menyetujui miliknya sendiri.
     *
     * @return Collection<int, User>
     */
    public function approversFor(PmRecord $record, SignatureStage $stage): Collection
    {
        if ($stage === SignatureStage::Pic) {
            $users = $record->picUser ? collect([$record->picUser]) : SignatureChain::picCandidates()->get();
        } else {
            $holders = User::permission($stage->permission())->get();
            $users = $holders->reject(fn (User $u) => $u->hasRole(Role::Admin->value));
            if ($users->isEmpty()) {
                $users = $holders;
            }
        }

        return $users
            ->reject(fn (User $u) => $record->technician_id !== null && $u->id === $record->technician_id)
            ->filter(fn (User $u) => filter_var($u->email, FILTER_VALIDATE_EMAIL))
            ->unique('id')
            ->values();
    }

    /** @param  Collection<int, User>  $users */
    private function send(Collection $users, \Illuminate\Notifications\Notification $notification): void
    {
        $users = $users->filter(fn ($u) => $u && filter_var($u->email, FILTER_VALIDATE_EMAIL));

        if ($users->isEmpty()) {
            return;
        }

        try {
            Notification::send($users, $notification);
        } catch (Throwable $e) {
            Log::warning('Gagal mengantrekan email notifikasi PM: '.$e->getMessage());
        }
    }
}
