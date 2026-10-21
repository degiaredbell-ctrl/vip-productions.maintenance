<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Informasi klien untuk Activity Logs: alamat IP yang benar, keterangan
 * jaringannya, dan ringkasan perangkat dari User-Agent.
 *
 * IP diambil dari Request::ip() yang menghormati daftar proxy tepercaya
 * (config/audit.php → TRUSTED_PROXIES). Aksi dari antrean/penjadwal tidak
 * punya klien, jadi IP-nya kosong dan ditandai "Sistem".
 */
class ClientInfo
{
    /** @return array{ip: ?string, user_agent: ?string} */
    public static function capture(?Request $request = null): array
    {
        // Job antrean, penjadwal, dan perintah artisan bukan permintaan dari
        // browser: Request::ip() di sana hanya 127.0.0.1 palsu.
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return ['ip' => null, 'user_agent' => null];
        }

        $request ??= request();
        $agent = trim((string) $request->userAgent());

        return [
            'ip' => $request->ip(),
            'user_agent' => $agent !== '' ? mb_substr($agent, 0, 255) : null,
        ];
    }

    /**
     * Keterangan alamat IP, supaya pembaca tahu apakah IP itu perangkat
     * pengguna atau titik jaringan (server, gateway NAT/VPN).
     */
    public static function describeIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        foreach (self::labels() as $range => $label) {
            if (IpUtils::checkIp($ip, $range)) {
                return $label;
            }
        }

        return match (true) {
            IpUtils::checkIp($ip, ['127.0.0.0/8', '::1']) => 'Server lokal',
            // Bridge Docker: permintaan dari server itu sendiri / antar container.
            IpUtils::checkIp($ip, '172.16.0.0/12') => 'Internal server (Docker)',
            IpUtils::isPrivateIp($ip) => 'Jaringan lokal',
            default => 'Internet',
        };
    }

    /**
     * Ringkasan perangkat dari User-Agent, mis. "Windows · Chrome".
     */
    public static function device(?string $agent): ?string
    {
        if ($agent === null || $agent === '') {
            return null;
        }

        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            (bool) preg_match('/iPhone|iPad|iPod/', $agent) => 'iOS',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'CrOS') => 'ChromeOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        $browser = match (true) {
            str_contains($agent, 'HeadlessChrome') => 'Headless Chrome (otomasi)',
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS') => 'Firefox',
            str_contains($agent, 'CriOS') || str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            (bool) preg_match('/curl|python|Guzzle|okhttp|wget/i', $agent) => 'Skrip / API',
            default => null,
        };

        $mobile = $os === 'Android' && str_contains($agent, 'Mobile') ? ' (ponsel)' : '';

        return trim(implode(' · ', array_filter([$os ? $os.$mobile : null, $browser]))) ?: 'Tidak dikenal';
    }

    /** @return array<string, string> ip/cidr => label */
    private static function labels(): array
    {
        $labels = [];

        foreach (explode(';', (string) config('audit.ip_labels', '')) as $pair) {
            [$range, $label] = array_pad(array_map('trim', explode('=', $pair, 2)), 2, '');
            if ($range !== '' && $label !== '') {
                $labels[$range] = $label;
            }
        }

        return $labels;
    }
}
