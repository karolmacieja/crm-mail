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
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'title' => $this->title,
            'due_date' => $this->due_date?->toIso8601String(),
            'is_completed' => $this->is_completed,
            'is_overdue' => ! $this->is_completed && $this->due_date !== null && $this->due_date->isPast(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact->id,
                'email' => $this->contact->email,
                'name' => $this->contact->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
