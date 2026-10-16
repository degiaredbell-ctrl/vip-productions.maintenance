<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * PIN login: tepat 6 digit angka, bukan pola yang mudah ditebak, dan belum
 * dipakai user lain (login PIN mencari user dari PIN-nya, jadi PIN harus unik).
 */
class ValidPin implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreUserId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        if (! preg_match('/^\d{'.User::PIN_LENGTH.'}$/', $value)) {
            $fail('PIN harus terdiri dari tepat '.User::PIN_LENGTH.' digit angka.');

            return;
        }

        if ($this->isWeak($value)) {
            $fail('PIN terlalu mudah ditebak. Hindari angka yang sama semua atau berurutan.');

            return;
        }

        $taken = User::where('pin_hash', User::pinDigest($value))
            ->when($this->ignoreUserId, fn ($q) => $q->where('id', '!=', $this->ignoreUserId))
            ->exists();

        if ($taken) {
            $fail('PIN ini tidak dapat digunakan. Silakan pilih PIN lain.');
        }
    }

    private function isWeak(string $pin): bool
    {
        if (count(array_unique(str_split($pin))) === 1) {
            return true;
        }

        return str_contains('0123456789', $pin) || str_contains('9876543210', $pin);
    }
}
