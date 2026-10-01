<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Activity */
class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,     // email | note | system
            'event' => $this->event,          // system events, e.g. "reservation.created"
            'title' => $this->title,
            'body' => $this->body,
            'meta' => $this->meta ?? (object) [],
            'subject_type' => $this->subject_type, // contact | reservation | task | reminder
            'subject_id' => $this->subject_id,
            'contact_id' => $this->contact_id,
            'author' => $this->whenLoaded('author', fn () => $this->author ? [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ] : null),
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
