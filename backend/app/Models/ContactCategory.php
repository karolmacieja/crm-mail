<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
use Database\Factories\ContactCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Per-group contact categories, e.g. B2B, VIP, Indywidualni.
 * is_private ("korespondencja firmowa"): every person keeps their own,
 * private card of such a contact; selected emails go to a team pool.
 */
class ContactCategory extends Model
{
    use BelongsToGroup;

    /** @use HasFactory<ContactCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'icon',
        'is_private',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ContactCategory $category) {
            if ($category->slug === null) {
                $base = Str::slug($category->name) ?: 'kategoria';
                $slug = $base;
                for ($i = 2; static::query()->forGroup($category->group_id)->where('slug', $slug)->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }
                $category->slug = $slug;
            }
            $category->sort_order ??= (int) static::query()->forGroup($category->group_id)->max('sort_order') + 1;
        });
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'category_id');
    }
}
