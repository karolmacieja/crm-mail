<?php

namespace App\Http\Resources;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Group */
class GroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'timezone' => $this->timezone,
            'contact_email' => $this->contact_email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'users_count' => $this->whenCounted('users'),
            'contacts_count' => $this->whenCounted('contacts'),
            'licenses' => LicenseResource::collection($this->whenLoaded('licenses')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
