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
