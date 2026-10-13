<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `can_any` — membolehkan request bila user memegang setidaknya
 * salah satu dari daftar permission. Dipakai untuk guard akses berbasis
 * "scope" aset (mesin vs utility) supaya role teknisi yang dibatasi
 * (tech-machines / tech-utilities) tidak perlu membawa `dashboard.view`,
 * sementara role general memakai `dashboard.view` sebagai baseline.
 *
 * Contoh pemakaian di routes/web.php:
 *   ->middleware('can_any:utility.view,dashboard.view')
 */
class EnsureAnyPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if ($request->user() && collect($permissions)->contains(
            fn (string $permission) => $request->user()->can($permission)
        )) {
            return $next($request);
        }

        abort(403, 'Akses tidak sesuai ruang lingkup role Anda.');
    }
}
