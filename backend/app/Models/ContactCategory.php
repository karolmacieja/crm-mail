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
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ContactCategory $category) {
            $category->slug ??= Str::slug($category->name);
        });
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'category_id');
    }
}
