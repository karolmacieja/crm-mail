<?php

namespace App\Http\Resources;

use App\Models\ContactShare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContactShare */
class ContactShareResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            // null = the whole team
            'user' => $this->user_id === null ? null : $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'scopes' => $this->scopes(),
            'email_ids' => array_map('intval', $this->activity_ids ?? []),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
