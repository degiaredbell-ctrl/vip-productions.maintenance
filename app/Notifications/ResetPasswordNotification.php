<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Isi email reset password (Bahasa Indonesia). Dipasang lewat
 * ResetPassword::toMailUsing() di AppServiceProvider, jadi alur bawaan
 * Laravel (token, kedaluwarsa, throttle) tetap dipakai.
 */
class ResetPasswordNotification
{
    public function __construct(private readonly string $token) {}

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Atur Ulang Password — PM Preventive')
            ->greeting('Halo, '.($notifiable->name ?? '').'!')
            ->line('Kami menerima permintaan untuk mengatur ulang password akun PM Preventive Anda.')
            ->action('Atur Ulang Password', $url)
            ->line("Tautan ini berlaku {$minutes} menit.")
            ->line('Jika Anda tidak meminta reset password, abaikan email ini. Password Anda tidak berubah.');
    }
}
