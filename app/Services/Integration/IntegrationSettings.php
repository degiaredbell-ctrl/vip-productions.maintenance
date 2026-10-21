<?php

namespace App\Services\Integration;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Pengaturan Settings → Integrate (email Microsoft 365, fitur email, laporan
 * otomatis), disimpan di tabel `app_settings`.
 *
 * Semua kunci punya nilai bawaan di DEFAULTS, jadi aplikasi tetap berjalan
 * sebelum halaman Integrate pernah disimpan (dan saat tabel belum ada, mis.
 * ketika migrasi baru berjalan). Nilai rahasia disimpan terenkripsi dan
 * tidak pernah dikirim balik ke browser.
 */
class IntegrationSettings
{
    public const SECRET_KEYS = ['graph.client_secret', 'smtp.password'];

    public const DEFAULTS = [
        // Koneksi
        'mail.enabled' => false,
        'mail.driver' => 'graph', // graph | smtp
        'mail.from_address' => '',
        'mail.from_name' => 'PM Preventive',
        'graph.tenant_id' => '',
        'graph.client_id' => '',
        'graph.client_secret' => '',
        'smtp.host' => 'smtp.office365.com',
        'smtp.port' => 587,
        'smtp.encryption' => 'tls',
        'smtp.username' => '',
        'smtp.password' => '',

        // Fitur akun
        'feature.password_reset' => true,
        'feature.login_otp' => false,
        'feature.login_otp_remember_days' => 30,

        // Notifikasi
        'notify.pm_awaiting' => true,
        'notify.pm_rejected' => true,
        'notify.pm_approved' => true,
        'notify.low_stock' => true,
        'notify.low_stock_recipients' => '',

        // Laporan otomatis
        'report.recipients' => '',
        'report.weekly_enabled' => false,
        'report.weekly_day' => 1, // 1 = Senin … 7 = Minggu
        'report.weekly_time' => '07:00',
        'report.monthly_enabled' => false,
        'report.monthly_day' => 1,
        'report.monthly_time' => '07:00',

        // Status uji koneksi terakhir
        'mail.last_test_at' => null,
        'mail.last_test_ok' => null,
        'mail.last_test_error' => null,
    ];

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $values = self::DEFAULTS;

        try {
            foreach (AppSetting::all() as $row) {
                if (! array_key_exists($row->key, self::DEFAULTS)) {
                    continue;
                }
                $values[$row->key] = $this->decode($row->key, $row->value, $row->is_encrypted);
            }
        } catch (Throwable) {
            // Tabel belum ada (migrasi berjalan) → pakai bawaan.
        }

        return $this->values = $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }

            $encrypted = in_array($key, self::SECRET_KEYS, true);

            AppSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $this->encode($value, $encrypted),
                    'is_encrypted' => $encrypted,
                ],
            );
        }

        $this->values = null;
    }

    /** Ada nilai rahasia tersimpan (untuk tampilan "tersimpan" di form). */
    public function hasSecret(string $key): bool
    {
        return (string) $this->get($key, '') !== '';
    }

    /** Koneksi email sudah diaktifkan dan isiannya lengkap. */
    public function mailReady(): bool
    {
        if (! $this->bool('mail.enabled') || trim((string) $this->get('mail.from_address')) === '') {
            return false;
        }

        if ($this->get('mail.driver') === 'smtp') {
            return trim((string) $this->get('smtp.host')) !== '';
        }

        return trim((string) $this->get('graph.tenant_id')) !== ''
            && trim((string) $this->get('graph.client_id')) !== ''
            && $this->hasSecret('graph.client_secret');
    }

    /** Fitur email aktif = koneksi siap + saklar fiturnya menyala. */
    public function featureOn(string $key): bool
    {
        return $this->mailReady() && $this->bool($key);
    }

    /**
     * Daftar alamat email dari isian teks (dipisah koma, titik koma, spasi,
     * atau baris baru). Alamat tidak valid dibuang.
     *
     * @return list<string>
     */
    public function recipients(string $key): array
    {
        return self::parseRecipients((string) $this->get($key, ''));
    }

    /** @return list<string> */
    public static function parseRecipients(string $value): array
    {
        return collect(preg_split('/[\s,;]+/', $value) ?: [])
            ->map(fn ($v) => strtolower(trim($v)))
            ->filter(fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function encode(mixed $value, bool $encrypted): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        return $encrypted && $string !== '' ? Crypt::encryptString($string) : $string;
    }

    private function decode(string $key, ?string $value, bool $encrypted): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($encrypted && $value !== '') {
            try {
                $value = Crypt::decryptString($value);
            } catch (Throwable) {
                return '';
            }
        }

        $default = self::DEFAULTS[$key];

        return match (true) {
            is_bool($default) => $value === '1',
            is_int($default) => (int) $value,
            default => $value,
        };
    }
}
