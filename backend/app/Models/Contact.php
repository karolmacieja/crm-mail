<?php

namespace App\Models;

use App\Enums\ContactStatus;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'phone',
        'status',
        'notes',
    ];

    protected $attributes = [
        'status' => 'lead',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
        ];
    }

    /**
     * Emails are stored lower-cased so Gmail lookups are case-insensitive.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtolower(trim($value)),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function openTasks(): HasMany
    {
        return $this->tasks()->where('is_completed', false);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('email', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }
}
