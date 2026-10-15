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
Schedule::command('backup:database --type=scheduled --prune')
    ->dailyAt(config('backup.schedule', '02:00'))
    ->withoutOverlapping()
    ->onOneServer();
