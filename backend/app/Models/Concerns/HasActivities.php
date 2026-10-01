<?php

namespace App\Models\Concerns;

use App\Enums\ActivityType;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Models that can be the subject of timeline entries (activities).
 */
trait HasActivities
{
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest('occurred_at');
    }

    /**
     * Record an automatic "system" timeline entry, e.g. 'reservation.created'.
     * The UI translates `event`; `meta` carries the values to interpolate.
     *
     * @param  array<string, mixed>  $meta
     */
    public function recordSystemActivity(string $event, array $meta = []): Activity
    {
        $activity = $this->activities()->make([
            'contact_id' => $this->contactIdForTimeline(),
            'user_id' => auth()->id(),
            'type' => ActivityType::System,
            'event' => $event,
            'meta' => $meta,
            'occurred_at' => now(),
        ]);
        // Always the subject's group (also when running without a tenant, e.g. in a queue job).
        $activity->group_id = $this->group_id;
        $activity->save();

        return $activity;
    }

    /** Which contact's timeline this subject's activities appear on. */
    protected function contactIdForTimeline(): ?int
    {
        return $this->getAttribute('contact_id');
    }
}
