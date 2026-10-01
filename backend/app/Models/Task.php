<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Models\Concerns\BelongsToGroup;
use App\Models\Concerns\HasActivities;
use App\Models\Concerns\HasDueWindows;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Work item ("Zadanie"), optionally about a contact and/or created from an email.
 */
class Task extends Model
{
    use BelongsToGroup, HasActivities, HasDueWindows;

    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    public const DUE_COLUMN = 'due_date';

    public const DONE_COLUMN = 'is_completed';

    protected $fillable = [
        'contact_id',
        'assigned_to',
        'title',
        'description',
        'type',
        'priority',
        'due_date',
        'is_completed',
        'source_email_id',
        'source_email_subject',
    ];

    protected $attributes = [
        'is_completed' => false,
        'type' => 'follow_up',
        'priority' => 'normal',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'type' => TaskType::class,
            'priority' => TaskPriority::class,
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

        static::updated(function (Task $task) {
            if ($task->wasChanged('is_completed') && $task->is_completed) {
                $task->recordSystemActivity('task.completed', ['title' => $task->title]);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @deprecated use creator(); kept for existing API code until step 2. */
    public function user(): BelongsTo
    {
        return $this->creator();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->pending();
    }

    /** "Pilne" column of the mockup: high priority, or due today / overdue. */
    public function scopeUrgent(Builder $query, ?string $timezone = null): Builder
    {
        return $query->pending()->where(function (Builder $q) use ($timezone) {
            $q->where('priority', TaskPriority::High)
                ->orWhere('due_date', '<=', static::endOfLocalDay($timezone));
        });
    }
}
