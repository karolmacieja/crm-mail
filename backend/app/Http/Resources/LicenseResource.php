<?php

namespace App\Http\Resources;

use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin License */
class LicenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'group' => $this->whenLoaded('group', fn () => ['id' => $this->group->id, 'name' => $this->group->name]),
            'key' => $this->key,
            'plan' => $this->plan,
            'seats' => $this->seats,
            'seats_used' => $this->whenCounted('users'),
            // active | expired | scheduled | suspended | cancelled
            'state' => $this->state(),
            'status' => $this->status->value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'notes' => $this->notes,
            'users' => $this->whenLoaded('users', fn () => $this->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'assigned_at' => $user->pivot->assigned_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
