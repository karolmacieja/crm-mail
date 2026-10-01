<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use Database\Factories\LicenseFactory;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A group's subscription with N seats. Seats are assigned to users through
 * the license_user table. Managed only from the web (Master Admin) panel.
 *
 * @property Carbon|null $starts_at
 * @property Carbon $expires_at
 * @property LicenseStatus $status
 */
class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'plan',
        'seats',
        'starts_at',
        'expires_at',
        'status',
        'notes',
    ];

    protected $attributes = [
        'plan' => 'standard',
        'seats' => 1,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => LicenseStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (License $license) {
            $license->key ??= static::generateKey();
        });
    }

    public static function generateKey(): string
    {
        do {
            $key = 'GFX-'.implode('-', str_split(Str::upper(Str::random(20)), 5));
        } while (static::where('key', $key)->exists());

        return $key;
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(LicenseAssignment::class)
            ->withPivot(['id', 'assigned_by', 'assigned_at']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LicenseStatus::Active)
            ->where('expires_at', '>', now())
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()));
    }

    public function isActive(): bool
    {
        return $this->status === LicenseStatus::Active
            && $this->expires_at->isFuture()
            && ($this->starts_at === null || $this->starts_at->isPast());
    }

    /** 'active' | 'expired' | 'suspended' | 'cancelled' | 'scheduled' */
    public function state(): string
    {
        return match (true) {
            $this->status !== LicenseStatus::Active => $this->status->value,
            $this->expires_at->isPast() => 'expired',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'scheduled',
            default => 'active',
        };
    }

    public function seatsUsed(): int
    {
        return $this->users()->count();
    }

    public function seatsAvailable(): int
    {
        return max(0, $this->seats - $this->seatsUsed());
    }

    /**
     * Extend by N days. An active license is extended from its current expiry,
     * so customers never lose paid time; an expired one restarts from now.
     */
    public function extend(int $days): static
    {
        $base = $this->expires_at !== null && $this->expires_at->isFuture() ? $this->expires_at : now();
        $this->expires_at = $base->copy()->addDays($days);
        $this->save();

        return $this;
    }

    /**
     * Give a seat to a user of the same group (moves them off any previous license).
     *
     * @throws DomainException when the user is in another group or no seat is free
     */
    public function assignTo(User $user, ?User $assignedBy = null): void
    {
        if ($user->group_id !== $this->group_id) {
            throw new DomainException('A license can only be assigned to users of its own group.');
        }

        DB::transaction(function () use ($user, $assignedBy) {
            // Lock the license row so two concurrent assignments can't oversell seats.
            static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if ($this->users()->whereKey($user->getKey())->exists()) {
                return;
            }

            if ($this->seatsAvailable() < 1) {
                throw new DomainException("No free seats left on license {$this->key}.");
            }

            LicenseAssignment::where('user_id', $user->getKey())->delete();
            $this->users()->attach($user->getKey(), [
                'assigned_by' => $assignedBy?->getKey(),
                'assigned_at' => now(),
            ]);
        });

        $user->unsetRelation('license');
    }

    public function unassign(User $user): void
    {
        $this->users()->detach($user->getKey());
        $user->unsetRelation('license');
    }
}
