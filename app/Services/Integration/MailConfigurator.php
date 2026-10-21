<?php

namespace App\Services\Integration;

use Illuminate\Mail\MailManager;

/**
 * Menerapkan pengaturan email dari Settings → Integrate ke konfigurasi mail
 * Laravel saat runtime.
 *
 * Dipanggil saat aplikasi boot dan sebelum setiap job antrean (worker hidup
 * lama, jadi perubahan pengaturan harus terbaca tanpa restart). Bila koneksi
 * belum siap, konfigurasi .env (default: `log`) dibiarkan apa adanya.
 */
class MailConfigurator
{
    public const MAILER = 'office365';

    public static function apply(?IntegrationSettings $settings = null): void
    {
        $settings ??= app(IntegrationSettings::class);

        if (! $settings->mailReady()) {
            return;
        }

        $from = [
            'address' => trim((string) $settings->get('mail.from_address')),
            'name' => trim((string) $settings->get('mail.from_name')) ?: config('app.name'),
        ];

        $mailer = $settings->get('mail.driver') === 'smtp'
            ? [
                'transport' => 'smtp',
                'host' => $settings->get('smtp.host'),
                'port' => (int) $settings->get('smtp.port'),
                'encryption' => $settings->get('smtp.encryption') ?: null,
                'username' => $settings->get('smtp.username') ?: $from['address'],
                'password' => $settings->get('smtp.password'),
                'timeout' => 30,
            ]
            : [
                'transport' => 'microsoft-graph',
                'tenant_id' => $settings->get('graph.tenant_id'),
                'client_id' => $settings->get('graph.client_id'),
                'client_secret' => $settings->get('graph.client_secret'),
                'sender' => $from['address'],
            ];

        config([
            'mail.default' => self::MAILER,
            'mail.mailers.'.self::MAILER => $mailer,
            'mail.from' => $from,
        ]);

        // Mailer yang sudah terlanjur dibuat memakai konfigurasi lama.
        app(MailManager::class)->purge(self::MAILER);
    }
}
