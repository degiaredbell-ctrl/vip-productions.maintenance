<?php

namespace App\Services\Notifications;

use App\Services\Integration\IntegrationSettings;

/**
 * Menentukan saluran yang benar-benar dipakai untuk satu penerima:
 * in-app (`database`) selalu bisa; email (`mail`) hanya bila koneksi email
 * di Settings → Integrate aktif dan akun punya alamat email valid.
 */
class NotificationChannels
{
    /** @return list<string> */
    public static function resolve(object $user, bool $app, bool $email): array
    {
        $channels = [];

        if ($app) {
            $channels[] = 'database';
        }

        if ($email && self::canEmail($user)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public static function canEmail(object $user): bool
    {
        return app(IntegrationSettings::class)->mailReady()
            && filter_var($user->email ?? null, FILTER_VALIDATE_EMAIL) !== false;
    }
}
