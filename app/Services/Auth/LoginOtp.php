<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use App\Services\Integration\IntegrationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;

/**
 * Verifikasi login dua langkah lewat kode email (OTP).
 *
 * Alur: password/PIN benar → user TIDAK langsung login; kode 6 digit dikirim
 * ke emailnya dan disimpan (ter-hash) di session → user memasukkan kode →
 * baru login. Perangkat bisa "diingat" lewat cookie terenkripsi sehingga
 * tidak diminta kode lagi selama N hari.
 *
 * OTP dilewati bila fitur nonaktif, koneksi email belum siap, atau akun tidak
 * punya email valid, supaya tidak ada user yang terkunci.
 */
class LoginOtp
{
    public const SESSION_KEY = 'login_otp';
    public const COOKIE = 'pm_trusted_device';
    public const TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_SECONDS = 60;

    public function __construct(private readonly IntegrationSettings $settings) {}

    public function required(User $user, Request $request): bool
    {
        return $this->settings->featureOn('feature.login_otp')
            && filter_var($user->email, FILTER_VALIDATE_EMAIL)
            && ! $this->isTrustedDevice($user, $request);
    }

    /** Mulai verifikasi: simpan tantangan di session lalu kirim kodenya. */
    public function start(User $user, bool $remember, Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'remember' => $remember,
            'intended' => $request->session()->get('url.intended'),
        ]);

        $this->send($request);
    }

    /** Kirim (ulang) kode baru. Kode lama tidak berlaku lagi. */
    public function send(Request $request): void
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        $user = $pending ? User::find($pending['user_id']) : null;

        if (! $user) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $request->session()->put(self::SESSION_KEY, [
            ...$pending,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
            'sent_at' => now()->getTimestamp(),
            'attempts' => 0,
        ]);

        $user->notifyNow(new LoginOtpNotification($code, self::TTL_MINUTES, $request->ip()));
    }

    /** @return array<string, mixed>|null */
    public function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return is_array($pending) && isset($pending['user_id']) ? $pending : null;
    }

    /**
     * Periksa kode. Mengembalikan user bila benar, atau pesan kesalahan.
     *
     * @return User|string
     */
    public function verify(Request $request, string $code): User|string
    {
        $pending = $this->pending($request);

        if (! $pending || ! isset($pending['code_hash'])) {
            return 'Sesi verifikasi berakhir. Silakan login ulang.';
        }

        if (now()->getTimestamp() > (int) $pending['expires_at']) {
            return 'Kode sudah kedaluwarsa. Kirim ulang kode baru.';
        }

        if ((int) $pending['attempts'] >= self::MAX_ATTEMPTS) {
            return 'Terlalu banyak percobaan. Kirim ulang kode baru.';
        }

        if (! Hash::check($code, $pending['code_hash'])) {
            $pending['attempts'] = (int) $pending['attempts'] + 1;
            $request->session()->put(self::SESSION_KEY, $pending);
            $left = self::MAX_ATTEMPTS - $pending['attempts'];

            return $left > 0 ? "Kode salah. Sisa {$left} percobaan." : 'Terlalu banyak percobaan. Kirim ulang kode baru.';
        }

        $user = User::find($pending['user_id']);

        return $user ?? 'Akun tidak ditemukan. Silakan login ulang.';
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function secondsUntilResend(Request $request): int
    {
        $sentAt = (int) ($this->pending($request)['sent_at'] ?? 0);

        return max(0, self::RESEND_SECONDS - (now()->getTimestamp() - $sentAt));
    }

    /** Tandai perangkat ini tepercaya selama N hari (cookie terenkripsi). */
    public function trustDevice(User $user): void
    {
        $days = max(1, (int) $this->settings->get('feature.login_otp_remember_days', 30));
        $expires = now()->addDays($days)->getTimestamp();

        Cookie::queue(self::COOKIE, $user->id.'|'.$expires.'|'.$this->fingerprint($user), $days * 24 * 60);
    }

    private function isTrustedDevice(User $user, Request $request): bool
    {
        $value = (string) $request->cookie(self::COOKIE, '');
        $parts = explode('|', $value);

        if (count($parts) !== 3) {
            return false;
        }

        [$id, $expires, $fingerprint] = $parts;

        return (int) $id === $user->id
            && (int) $expires > now()->getTimestamp()
            && hash_equals($this->fingerprint($user), $fingerprint);
    }

    /** Berubah saat password diganti, jadi perangkat lama harus verifikasi lagi. */
    private function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', $user->id.'|'.$user->password, config('app.key')), 0, 32);
    }

    public function maskedEmail(Request $request): string
    {
        $email = (string) User::find($this->pending($request)['user_id'] ?? 0)?->email;
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return $name === '' ? '' : substr($name, 0, 2).str_repeat('•', max(1, strlen($name) - 2)).'@'.$domain;
    }
}
