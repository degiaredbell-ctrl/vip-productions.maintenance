<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kode verifikasi login (OTP) 6 digit. Dikirim langsung (tidak lewat
 * antrean) karena user sedang menunggu di layar verifikasi.
 */
class LoginOtpNotification extends Notification
{
    public function __construct(
        public readonly string $code,
        private readonly int $minutes,
        private readonly ?string $ip = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Kode verifikasi login: {$this->code}")
            ->greeting('Halo, '.($notifiable->name ?? '').'!')
            ->line('Gunakan kode berikut untuk menyelesaikan login ke PM Preventive:')
            ->line("**{$this->code}**")
            ->line("Kode berlaku {$this->minutes} menit dan hanya bisa dipakai sekali.");

        if ($this->ip) {
            $message->line('Permintaan login dari alamat IP: '.$this->ip.' pada '.now()->format('d/m/Y H:i').' WIB.');
        }

        return $message->line('Jika bukan Anda yang mencoba login, segera ganti password dan hubungi admin.');
    }
}
