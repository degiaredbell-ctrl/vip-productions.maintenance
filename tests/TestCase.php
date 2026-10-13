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

        /*
            Sama seperti koneksi DB: APP_ENV di phpunit.xml hanya menyentuh
            $_ENV, sedangkan container mengisi $_SERVER lebih dulu dan env()
            membacanya dari sana. Akibatnya app tetap dianggap production,
            lalu migrate:fresh (dipakai RefreshDatabase) dibatalkan oleh
            pengaman "APPLICATION IN PRODUCTION" dan seluruh test DB gagal
            dengan "no such table: users". Ditetapkan lewat config agar
            phpunit selalu berjalan sebagai environment testing.
        */
        $app['config']->set('app.env', 'testing');

        /*
            config('app.env') saja tidak cukup: Application::isProduction()
            membaca binding container 'env' yang diisi tahap DetectEnvironment,
            bukan config. Tanpa baris ini app()->isProduction() tetap true dan
            migrate:fresh dibatalkan.
        */
        $app['env'] = 'testing';

        /*
            phpunit.xml menetapkan SESSION_DRIVER=array, tapi sama seperti
            APP_ENV, nilainya kalah oleh $_SERVER dari container. Akibatnya
            request tanpa RefreshDatabase (mis. ExampleTest) menabrak tabel
            sessions yang tidak ada. Dipaksa lewat config agar konsisten.
        */
        $app['config']->set('session.driver', 'array');

        return $app;
    }
}