<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Database
    |--------------------------------------------------------------------------
    |
    | Konfigurasi fitur Backup & Restore (khusus database). Backup disimpan
    | sebagai file .sql di disk privat `storage/app/backups`, tidak pernah
    | terekspos ke publik. Pembuatan dan pemulihan dijalankan dari halaman
    | admin (permission `backup.manage`) atau dari penjadwalan harian.
    |
    */

    // Disk tempat file backup disimpan (lihat config/filesystems.php).
    'disk' => env('BACKUP_DISK', 'backups'),

    // Retensi: jumlah backup terbaru yang disimpan saat prune dijalankan.
    'keep' => (int) env('BACKUP_KEEP', 30),

    // Waktu penjadwalan harian otomatis (format HH:MM, zona waktu APP_TIMEZONE).
    'schedule' => env('BACKUP_SCHEDULE', '02:00'),

    // Zona waktu untuk MENAMPILKAN waktu backup ke pengguna. Timestamp tetap
    // disimpan di database dalam UTC (APP_TIMEZONE) supaya konsisten; konversi
    // hanya dilakukan saat ditampilkan, mis. pada kolom "Waktu" Daftar Backup
    // dan nama berkas backup.
    'timezone' => env('BACKUP_TIMEZONE', 'Asia/Jakarta'),

];
