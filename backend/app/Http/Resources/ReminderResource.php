<?php

namespace App\Http\Resources;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reminder */
class ReminderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id, // created by
            'type' => $this->type->value,
            'title' => $this->title,
            'notes' => $this->notes,
            'remind_at' => $this->remind_at->toIso8601String(),
            'is_done' => $this->is_done,
            'done_at' => $this->done_at?->toIso8601String(),
            // overdue | today | upcoming | later | done  (restaurant timezone)
            'time_status' => $this->timeStatus($request->user()?->group?->timezone),
            'contact_id' => $this->contact_id,
            'reservation_id' => $this->reservation_id,
            'source_email_id' => $this->source_email_id,
            'source_email_subject' => $this->source_email_subject,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'id' => $this->contact->id,
                'email' => $this->contact->email,
                'name' => $this->contact->name,
            ] : null),
            'reservation' => $this->whenLoaded('reservation', fn () => $this->reservation?->summary()),
            // Event in the signed-in person's own Google Calendar (eager-loaded for them only).
            'calendar_event_id' => $this->whenLoaded('calendarEvents', fn () => $this->calendarEvents->first()?->external_id),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
