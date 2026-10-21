<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Integration\IntegrationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        $this->ensureEnabled();

        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $request->validate([
            'email' => 'required|email',
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Pesan sama untuk email terdaftar maupun tidak, supaya formulir ini
        // tidak bisa dipakai menebak email siapa saja yang punya akun.
        if ($status == Password::RESET_LINK_SENT || $status == Password::INVALID_USER) {
            return back()->with('status', 'Jika email terdaftar, tautan reset password sudah dikirim. Periksa kotak masuk (dan folder Junk) Anda.');
        }

        throw ValidationException::withMessages([
            'email' => [$status == Password::RESET_THROTTLED
                ? 'Tunggu sebentar sebelum meminta tautan reset lagi.'
                : 'Tautan reset password tidak dapat dikirim. Coba lagi nanti.'],
        ]);
    }

    /** Lupa password hanya tersedia bila diaktifkan di Settings → Integrate. */
    private function ensureEnabled(): void
    {
        abort_unless(
            app(IntegrationSettings::class)->featureOn('feature.password_reset'),
            404,
            'Fitur lupa password belum diaktifkan. Hubungi admin.'
        );
    }
}
