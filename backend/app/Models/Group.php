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

    public const DEFAULT_STATUSES = [
        ['key' => 'lead', 'name' => 'Lead', 'color' => 'sky', 'sort_order' => 1, 'is_default' => true],
        ['key' => 'prospect', 'name' => 'Potencjalny klient', 'color' => 'amber', 'sort_order' => 2],
        ['key' => 'customer', 'name' => 'Klient', 'color' => 'emerald', 'sort_order' => 3],
        ['key' => 'inactive', 'name' => 'Nieaktywny', 'color' => 'gray', 'sort_order' => 4],
    ];

    public const DEFAULT_TASK_CATEGORIES = [
        ['key' => 'follow_up', 'name' => 'Kontakt i Follow-up', 'color' => 'blue', 'icon' => 'phone-volume', 'sort_order' => 1],
        ['key' => 'offer', 'name' => 'Oferty', 'color' => 'orange', 'icon' => 'file-invoice', 'sort_order' => 2],
        ['key' => 'internal', 'name' => 'Wewnętrzne (Restauracja)', 'color' => 'gray', 'icon' => 'utensils', 'sort_order' => 3],
    ];

    public const DEFAULT_FIELD_TEMPLATES = [
        ['label' => 'Alergie', 'type' => 'text', 'sort_order' => 1],
        ['label' => 'NIP', 'type' => 'text', 'sort_order' => 2],
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

        // Every new restaurant starts with the same editable dictionaries.
        static::created(function (Group $group) {
            foreach (self::DEFAULT_CATEGORIES as $category) {
                $group->contactCategories()->create($category);
            }
            foreach (self::DEFAULT_STATUSES as $status) {
                $group->contactStatuses()->create($status);
            }
            foreach (self::DEFAULT_TASK_CATEGORIES as $category) {
                $group->taskCategories()->create($category);
            }
            foreach (self::DEFAULT_FIELD_TEMPLATES as $template) {
                $group->customFieldTemplates()->create($template);
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

    public function contactStatuses(): HasMany
    {
        return $this->hasMany(ContactStatus::class)->orderBy('sort_order');
    }

    public function taskCategories(): HasMany
    {
        return $this->hasMany(TaskCategory::class)->orderBy('sort_order');
    }

    public function customFieldTemplates(): HasMany
    {
        return $this->hasMany(CustomFieldTemplate::class)->orderBy('sort_order');
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
