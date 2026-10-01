<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string|null $license_key
 * @property Carbon|null $license_expires_at
 * @property bool $is_admin
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * License and admin columns are deliberately NOT mass assignable;
     * they are only changed through grantLicense()/revokeLicense() or by admins.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'license_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'license_expires_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function hasValidLicense(): bool
    {
        return $this->license_key !== null
            && $this->license_expires_at !== null
            && $this->license_expires_at->isFuture();
    }

    /**
     * Machine-readable license state used by the API and the extension UI.
     */
    public function licenseStatus(): string
    {
        if ($this->license_key === null || $this->license_expires_at === null) {
            return 'missing';
        }

        return $this->license_expires_at->isFuture() ? 'active' : 'expired';
    }

    /**
     * Issue (or extend) a license. Extending an active license adds time
     * on top of the current expiry so customers never lose paid days.
     */
    public function grantLicense(int $days, bool $regenerateKey = false): void
    {
        $base = $this->hasValidLicense() ? $this->license_expires_at : now();

        if ($this->license_key === null || $regenerateKey) {
            $this->license_key = static::generateLicenseKey();
        }

        $this->license_expires_at = $base->copy()->addDays($days);
        $this->save();
    }

    public function revokeLicense(): void
    {
        $this->license_expires_at = now();
        $this->save();
    }

    public static function generateLicenseKey(): string
    {
        do {
            $key = 'CRM-'.implode('-', str_split(Str::upper(Str::random(20)), 5));
        } while (static::where('license_key', $key)->exists());

        return $key;
    }
}
