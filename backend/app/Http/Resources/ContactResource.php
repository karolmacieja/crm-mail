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
        $access = $request->user() ? $this->accessFor($request->user()) : null;
        // Without the "details" section a colleague sees who the client is, not their data.
        $details = $access?->has('details') ?? true;

        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'company' => $this->when($details, $this->company),
            'phone' => $this->when($details, $this->phone),
            'status' => $this->status,
            'is_client' => $this->is_client,
            'notes' => $this->when($details, $this->notes),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'access' => $this->when($access !== null, fn () => [
                'is_owner' => $this->user_id !== null && $this->user_id === $request->user()->id,
                'can_manage' => $access->canManage,
                'scopes' => $access->scopes,
                'email_ids' => $access->activityIds,
            ]),
            'shares' => ContactShareResource::collection($this->whenLoaded('shares')),
            'access_requests' => $this->whenLoaded('accessRequests', fn () => $this->accessRequests->map(fn ($r) => [
                'id' => $r->id,
                'user' => ['id' => $r->requester->id, 'name' => $r->requester->name],
                'message' => $r->message,
                'created_at' => $r->created_at?->toIso8601String(),
            ])),
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
            'notes_count' => $this->whenCounted('notes'),
            'recent_notes' => ActivityResource::collection($this->whenLoaded('recentNotes')),
            'email_history_synced_at' => $this->email_history_synced_at?->toIso8601String(),
            'custom_fields' => $this->when($details, fn () => ContactCustomFieldResource::collection($this->whenLoaded('customFields'))),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'reminders' => ReminderResource::collection($this->whenLoaded('reminders')),
            'upcoming_reservations' => ReservationResource::collection($this->whenLoaded('upcomingReservations')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
