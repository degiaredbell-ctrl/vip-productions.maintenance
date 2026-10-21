<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PinLoginRequest;
use App\Services\Auth\LoginOtp;
use App\Services\Integration\IntegrationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            // Lupa password hanya ditawarkan bila email Microsoft 365 aktif
            // dan fiturnya dinyalakan di Settings → Integrate.
            'canResetPassword' => Route::has('password.request')
                && app(IntegrationSettings::class)->featureOn('feature.password_reset'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        return $this->completeLogin($request);
    }

    /**
     * Login alternatif memakai PIN 6 digit saja.
     */
    public function storePin(PinLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        return $this->completeLogin($request);
    }

    /**
     * Password/PIN sudah benar. Bila verifikasi kode email (OTP) aktif, login
     * ditahan dulu sampai kode dimasukkan; selain itu langsung masuk.
     */
    private function completeLogin(Request $request): RedirectResponse
    {
        $user = $request->user();
        $otp = app(LoginOtp::class);

        if ($user && $otp->required($user, $request)) {
            Auth::guard('web')->logout();
            $request->session()->regenerate();

            try {
                $otp->start($user, $request->boolean('remember'), $request);
            } catch (\Throwable $e) {
                report($e);

                return redirect()->route('login.otp')->with('error', 'Kode verifikasi gagal dikirim. Coba "Kirim ulang kode" atau hubungi admin.');
            }

            return redirect()->route('login.otp');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
