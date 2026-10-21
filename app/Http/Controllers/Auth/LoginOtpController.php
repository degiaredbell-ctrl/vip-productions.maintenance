<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginOtp;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Langkah kedua login: memasukkan kode verifikasi yang dikirim ke email.
 */
class LoginOtpController extends Controller
{
    public function __construct(private readonly LoginOtp $otp) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if (! $this->otp->pending($request)) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/LoginOtp', [
            'email' => $this->otp->maskedEmail($request),
            'resendIn' => $this->otp->secondsUntilResend($request),
            'rememberDays' => (int) app(\App\Services\Integration\IntegrationSettings::class)->get('feature.login_otp_remember_days', 30),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']], [
            'code.required' => 'Masukkan 6 digit kode.',
            'code.digits' => 'Kode terdiri dari 6 angka.',
        ]);

        $pending = $this->otp->pending($request);
        $result = $this->otp->verify($request, $request->string('code'));

        if (is_string($result)) {
            if (! $pending) {
                return redirect()->route('login')->withErrors(['email' => $result]);
            }

            throw ValidationException::withMessages(['code' => $result]);
        }

        $this->otp->forget($request);

        Auth::login($result, (bool) ($pending['remember'] ?? false));
        $request->session()->regenerate();

        if (! empty($pending['intended'])) {
            $request->session()->put('url.intended', $pending['intended']);
        }

        if ($request->boolean('trust_device')) {
            $this->otp->trustDevice($result);
        }

        AuditLogService::record('auth.login_otp', \App\Models\User::class, $result->id, [
            'ip' => $request->ip(),
            'trusted_device' => $request->boolean('trust_device'),
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $this->otp->pending($request)) {
            return redirect()->route('login');
        }

        if (($wait = $this->otp->secondsUntilResend($request)) > 0) {
            return back()->with('error', "Tunggu {$wait} detik sebelum mengirim ulang kode.");
        }

        try {
            $this->otp->send($request);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Kode gagal dikirim. Periksa koneksi email atau hubungi admin.');
        }

        return back()->with('status', 'Kode baru sudah dikirim ke email Anda.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->otp->forget($request);

        return redirect()->route('login');
    }
}
