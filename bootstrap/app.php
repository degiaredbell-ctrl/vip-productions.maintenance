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
        // Halaman error beranimasi (resources/js/Pages/Errors/Error.jsx)
        // menggantikan halaman error bawaan Laravel / modal error Inertia.
        // Permintaan JSON murni (axios/fetch) tetap mendapat respons JSON.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            $status = $response->getStatusCode();
            $inertia = $request->hasHeader('X-Inertia');

            if ($request->expectsJson() && ! $inertia) {
                return $response;
            }

            // Sesi/CSRF kedaluwarsa saat menekan tombol di aplikasi: kembali
            // ke halaman sebelumnya dengan pesan, bukan halaman error kosong.
            if ($status === 419 && $inertia) {
                return back()->with('error', 'Sesi Anda kedaluwarsa. Silakan ulangi tindakan Anda.');
            }

            // Saat pengembangan lokal, error 500 tetap menampilkan detail debug.
            if ($status >= 500 && app()->environment('local') && config('app.debug')) {
                return $response;
            }

            if (! in_array($status, [403, 404, 405, 419, 429, 500, 503], true)) {
                return $response;
            }

            // Pesan dari abort(403, '...') aplikasi (berbahasa Indonesia) ikut
            // ditampilkan; pesan bawaan framework/paket (Inggris) tidak.
            $message = $status < 500 ? trim($e->getMessage()) : '';
            $generic = ['This action is unauthorized.', 'User does not have the right permissions.', 'User does not have the right roles.', 'Forbidden', 'Not Found', 'CSRF token mismatch.', 'Too Many Attempts.', 'Service Unavailable', 'Unauthorized.'];
            if ($message === '' || in_array($message, $generic, true) || str_starts_with($message, 'No query results') || str_starts_with($message, 'The route ') || str_starts_with($message, 'The ') && str_contains($message, 'method is not supported')) {
                $message = null;
            }

            return \Inertia\Inertia::render('Errors/Error', [
                'status' => $status,
                'message' => $message,
                // "/" mengarah ke Beranda (sudah login) atau Login (tamu).
                'homeUrl' => url('/'),
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
