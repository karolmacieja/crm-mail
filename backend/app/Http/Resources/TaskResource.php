<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timeStatus = $this->timeStatus($request->user()?->group?->timezone);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id, // created by
            'contact_id' => $this->contact_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority->value,
            'due_date' => $this->due_date?->toIso8601String(),
            'is_completed' => $this->is_completed,
            'is_overdue' => $timeStatus === 'overdue',
            'time_status' => $timeStatus,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'source_email_id' => $this->source_email_id,
            'source_email_subject' => $this->source_email_subject,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'id' => $this->contact->id,
                'email' => $this->contact->email,
                'name' => $this->contact->name,
            ] : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
            // Event in the signed-in person's own Google Calendar (eager-loaded for them only).
            'calendar_event_id' => $this->whenLoaded('calendarEvents', fn () => $this->calendarEvents->first()?->external_id),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
