<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Spatie tidak mendaftarkan middleware-nya sendiri sejak v6, padahal
        // routes/web.php memakai `role:admin` untuk grup /admin/roles. Tanpa
        // alias di bawah, halaman itu balas 500 "Target class [role]".
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            // Guard "punya setidaknya satu permission" untuk akses berbasis
            // scope mesin vs utility (lihat EnsureAnyPermission).
            'can_any' => \App\Http\Middleware\EnsureAnyPermission::class,
        ]);

        // IP klien yang akurat di belakang reverse proxy / load balancer:
        // header X-Forwarded-* hanya dipercaya bila datang dari alamat di
        // TRUSTED_PROXIES (config/audit.php). Tanpa daftar ini header itu
        // diabaikan supaya IP di Activity Logs tidak bisa dipalsukan klien.
        $proxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))));
        if ($proxies !== []) {
            $middleware->trustProxies(
                at: $proxies,
                headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
