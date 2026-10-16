<?php

namespace App\Models;

use App\Enums\Role;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'signature_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin_hash',
        'remember_token',
    ];

    public const PIN_LENGTH = 6;

    /**
     * Digest PIN yang disimpan di kolom pin_hash. Deterministik (HMAC dengan
     * APP_KEY) supaya user bisa dicari dari PIN saja dan PIN bisa dijaga unik.
     */
    public static function pinDigest(string $pin): string
    {
        return hash_hmac('sha256', $pin, (string) config('app.key'));
    }

    public static function findByPin(string $pin): ?self
    {
        return static::where('pin_hash', static::pinDigest($pin))->first();
    }

    public function setPin(?string $pin): void
    {
        $this->forceFill(['pin_hash' => $pin === null ? null : static::pinDigest($pin)])->save();
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roleLabel(): string
    {
        return $this->roles->first()?->name ?? '-';
    }

    public function pmRecords(): HasMany
    {
        return $this->hasMany(PmRecord::class, 'technician_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
