<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup database harian otomatis. Retensi dijaga lewat `--prune` supaya
// backup lama ikut dibersihkan tanpa perlu intervensi manual. Jalankan
// `php artisan schedule:work` (atau cron `schedule:run`) agar berjalan.
// `dailyAt` mengikuti zona waktu aplikasi (UTC), sedangkan jam yang ditampilkan
// di halaman Backup adalah zona waktu tampilan (default Asia/Jakarta). Setel
// timezone eksplisit supaya "pukul 02:00" benar-benar 02:00 WIB, bukan 09:00 WIB.
Schedule::command('backup:database --type=scheduled --prune')
    ->dailyAt(config('backup.schedule', '02:00'))
    ->timezone(config('backup.timezone', 'Asia/Jakarta'))
    ->withoutOverlapping()
    ->onOneServer();

// Notifikasi & laporan PM (Settings → Notifications). Jadwal dibaca dari
// pengaturan setiap kali scheduler berjalan, jadi perubahan jam di halaman
// Notifications langsung berlaku tanpa restart.
try {
    $integration = app(\App\Services\Integration\IntegrationSettings::class);
    $validTime = fn ($t) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', trim((string) $t)) === 1;

    // 1. Reminder Minggu ke-2: dicek setiap hari pada jam yang diatur; command
    //    sendiri yang memastikan hanya terkirim sekali saat Minggu ke-2.
    if ($integration->bool('notif.reminder.enabled') && $validTime($integration->get('notif.reminder.time'))) {
        Schedule::command('notifications:pm-alert reminder')
            ->dailyAt(trim((string) $integration->get('notif.reminder.time')))
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping()
            ->onOneServer();
    }

    // 2. Keterlambatan PM: diulang setiap hari pada tiap jam (default 07:00 & 14:00).
    if ($integration->bool('notif.overdue.enabled')) {
        foreach (array_unique(array_filter(array_map('trim', explode(',', (string) $integration->get('notif.overdue.times'))), $validTime)) as $time) {
            Schedule::command('notifications:pm-alert overdue')
                ->dailyAt($time)
                ->timezone('Asia/Jakarta')
                ->withoutOverlapping()
                ->onOneServer();
        }
    }

    if ($integration->mailReady() && $integration->bool('report.weekly_enabled')) {
        Schedule::command('integration:send-pm-report weekly')
            ->weeklyOn((int) $integration->get('report.weekly_day') % 7, (string) $integration->get('report.weekly_time'))
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping()
            ->onOneServer();
    }

    if ($integration->mailReady() && $integration->bool('report.monthly_enabled')) {
        Schedule::command('integration:send-pm-report monthly')
            ->monthlyOn((int) $integration->get('report.monthly_day'), (string) $integration->get('report.monthly_time'))
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping()
            ->onOneServer();
    }
} catch (\Throwable) {
    // Pengaturan belum bisa dibaca (mis. saat migrasi pertama) → lewati.
}

// Darurat: matikan verifikasi kode email bila email bermasalah dan admin
// tidak bisa login. Jalankan: php artisan integration:disable-otp
Artisan::command('integration:disable-otp', function () {
    app(\App\Services\Integration\IntegrationSettings::class)->set(['feature.login_otp' => false]);
    $this->info('Verifikasi login lewat kode email dinonaktifkan.');
})->purpose('Nonaktifkan verifikasi login (OTP) email — untuk keadaan darurat');
