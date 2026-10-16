<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PinLoginRequest extends FormRequest
{
    /**
     * Ruang PIN 6 digit kecil dan login tidak memakai email, jadi percobaan
     * dibatasi per IP (bukan per akun) supaya PIN tidak bisa ditebak beruntun.
     */
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $pin = (string) $this->input('pin');
        $user = preg_match('/^\d{'.User::PIN_LENGTH.'}$/', $pin) ? User::findByPin($pin) : null;

        if ($user === null) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'pin' => 'PIN salah atau belum diatur.',
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'pin' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return 'pin-login|'.$this->ip();
    }
}
