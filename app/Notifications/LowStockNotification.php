<?php

namespace App\Notifications;

use App\Models\SparePart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Peringatan stok suku cadang habis / menipis. Dikirim sekali saat stok
 * melewati batas (bukan setiap transaksi), ke penerima peringatan stok di
 * Settings → Integrate.
 */
class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly SparePart $part)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $part = $this->part->fresh() ?? $this->part;
        $out = $part->stock <= 0;
        $label = "{$part->code} — {$part->name}";

        return (new MailMessage)
            ->level($out ? 'error' : 'default')
            ->subject(($out ? 'Stok HABIS: ' : 'Stok menipis: ').$label)
            ->greeting($out ? 'Stok suku cadang habis' : 'Stok suku cadang menipis')
            ->line("**{$label}**")
            ->line("Sisa stok: **{$part->stock} {$part->unit}** · batas minimum: {$part->min_stock} {$part->unit}")
            ->line('Lokasi: '.($part->location ?: '-').' · Supplier: '.($part->supplier ?: '-'))
            ->line('Pesan minimal: **'.max($part->min_stock - $part->stock + 1, 1)." {$part->unit}** agar kembali di atas batas minimum.")
            ->action('Buka Dashboard Suku Cadang', route('spareparts.dashboard'));
    }
}
