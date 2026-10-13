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

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
