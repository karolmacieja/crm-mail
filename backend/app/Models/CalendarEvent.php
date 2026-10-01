<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Link between a task/reminder and the event the extension created in a
 * person's own calendar (Google Calendar API), so it can be updated/removed.
 */
class CalendarEvent extends Model
{
    protected $fillable = ['user_id', 'provider', 'external_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }
}
