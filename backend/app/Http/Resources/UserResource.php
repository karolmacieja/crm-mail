<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_admin' => $this->is_admin,
            'license' => [
                'status' => $this->licenseStatus(),
                'is_valid' => $this->is_admin || $this->hasValidLicense(),
                'expires_at' => $this->license_expires_at?->toIso8601String(),
                // The full key is only visible to admins; owners see a masked version.
                'key' => $this->when(
                    $this->license_key !== null,
                    fn () => $request->user()?->is_admin
                        ? $this->license_key
                        : substr($this->license_key, 0, 9).str_repeat('*', 5).substr($this->license_key, -5),
                ),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
