<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__ . '/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        /*
            Paksa koneksi database test ke sqlite in-memory, apa pun isi .env
            atau variabel shell.

            Menandainya lewat <env> di phpunit.xml saja tidak cukup: PHPUnit
            menulis ke getenv() dan $_ENV, tapi PHP CLI tetap mengisi $_SERVER
            dari shell, dan env() membaca $_SERVER lebih dulu. Akibatnya
            RefreshDatabase (yang memakai migrate:fresh) sempat berjalan di
            database produksi dan menghapus seluruh akun, mesin, dan riwayat PM.

            Ditetapkan lewat config, jadi tidak ada jalur yang bisa ditembus dari
            luar. Butuh ekstensi pdo_sqlite.
        */
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        return $app;
    }
}