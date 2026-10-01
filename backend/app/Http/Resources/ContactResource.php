<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Contact */
class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'company' => $this->company,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'is_client' => $this->is_client,
            'notes' => $this->notes,
            'category_id' => $this->category_id,
            'category' => new ContactCategoryResource($this->whenLoaded('category')),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'last_activity' => $this->whenLoaded('latestActivity', fn () => $this->latestActivity ? [
                'type' => $this->latestActivity->type->value,
                'event' => $this->latestActivity->event,
                'direction' => $this->latestActivity->meta['direction'] ?? null,
                'occurred_at' => $this->latestActivity->occurred_at->toIso8601String(),
            ] : null),
            'open_tasks_count' => $this->whenCounted('openTasks'),
            'custom_fields' => ContactCustomFieldResource::collection($this->whenLoaded('customFields')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'reminders' => ReminderResource::collection($this->whenLoaded('reminders')),
            'upcoming_reservations' => ReservationResource::collection($this->whenLoaded('upcomingReservations')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
