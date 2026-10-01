<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'title',
        'due_date',
        'is_completed',
    ];

    protected $attributes = [
        'is_completed' => false,
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep completed_at in sync with the is_completed flag.
        static::saving(function (Task $task) {
            if ($task->isDirty('is_completed')) {
                $task->completed_at = $task->is_completed ? now() : null;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_completed', false);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_date')->where('due_date', '<', now());
    }
}
