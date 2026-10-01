<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A tenant: one restaurant (or restaurant group) using the CRM.
 *
 * Relations to tenant-owned models bypass nothing: GroupScope still applies,
 * so a group's admin view should be built with ->forGroup() or Tenancy::runAs().
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /** Created for every new group (mirrors the categories in the mockup). */
    public const DEFAULT_CATEGORIES = [
        ['name' => 'B2B', 'slug' => 'b2b', 'color' => 'blue', 'icon' => 'fa-briefcase', 'sort_order' => 1],
        ['name' => 'VIP', 'slug' => 'vip', 'color' => 'purple', 'icon' => 'fa-star', 'sort_order' => 2],
        ['name' => 'Indywidualni', 'slug' => 'indywidualni', 'color' => 'green', 'icon' => 'fa-user', 'sort_order' => 3],
    ];

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'contact_email',
        'phone',
        'is_active',
    ];

    protected $attributes = [
        'timezone' => 'Europe/Warsaw',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Group $group) {
            $group->slug ??= static::uniqueSlug($group->name);
        });

        static::created(function (Group $group) {
            foreach (self::DEFAULT_CATEGORIES as $category) {
                $group->contactCategories()->create($category);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'grupa';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function contactCategories(): HasMany
    {
        return $this->hasMany(ContactCategory::class)->orderBy('sort_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** The license currently in force for this group, if any. */
    public function activeLicense(): ?License
    {
        return $this->licenses()->active()->orderByDesc('expires_at')->first();
    }
}
