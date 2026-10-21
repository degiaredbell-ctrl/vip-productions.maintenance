<?php

namespace App\Services\Integration;

use App\Models\SparePart;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Notifikasi email non-PM (peringatan stok). Notifikasi PM — approval,
 * jadwal, keterlambatan, laporan — dikelola modul Settings → Notifications
 * (App\Services\Notifications).
 */
class MailNotifier
{
    public function __construct(private readonly IntegrationSettings $settings) {}

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
}
