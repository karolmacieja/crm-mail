<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int|null $group_id
 * @property UserRole $role
 * @property-read License|null $license
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * group_id and role are deliberately NOT mass assignable:
     * only the Master Admin panel changes them, explicitly.
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
    ];

    protected $attributes = [
        'role' => 'staff',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** The license whose seat this user holds (at most one). */
    public function license(): HasOneThrough
    {
        return $this->hasOneThrough(License::class, LicenseAssignment::class, 'user_id', 'id', 'id', 'license_id');
    }

    /** Contacts this user created (all group members see every group contact). */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /** Tasks this user created. */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function isMasterAdmin(): bool
    {
        return $this->role === UserRole::MasterAdmin;
    }

    public function hasValidLicense(): bool
    {
        return (bool) $this->license?->isActive();
    }

    /** 'active' | 'expired' | 'suspended' | 'cancelled' | 'scheduled' | 'missing' */
    public function licenseStatus(): string
    {
        return $this->license?->state() ?? 'missing';
    }

    /**
     * Ensure this user holds a seat and extend that license by N days.
     * Creates a 1-seat license for the user's group when it has none with a free seat.
     */
    public function grantLicense(int $days, bool $regenerateKey = false, ?User $grantedBy = null): License
    {
        if ($this->group_id === null) {
            throw new DomainException('Users without a group cannot hold a license.');
        }

        $license = $this->license
            ?? $this->group->licenses()->active()->get()->first(fn (License $l) => $l->seatsAvailable() > 0)
            ?? $this->group->licenses()->create(['seats' => 1, 'starts_at' => now(), 'expires_at' => now()]);

        if ($regenerateKey) {
            $license->key = License::generateKey();
        }

        $license->extend($days);
        $license->assignTo($this, $grantedBy);

        return $license;
    }

    /** Remove this user's seat (the group's license itself stays). */
    public function revokeLicense(): void
    {
        $this->license?->unassign($this);
    }
}
