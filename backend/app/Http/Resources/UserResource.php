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
        $license = $this->license;
        $viewerIsAdmin = (bool) $request->user()?->isMasterAdmin();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_admin' => $this->isMasterAdmin(),
            'group' => $this->group_id === null ? null : [
                'id' => $this->group_id,
                'name' => $this->group?->name,
                'timezone' => $this->group?->timezone,
            ],
            'license' => [
                'status' => $this->licenseStatus(),
                'is_valid' => $this->isMasterAdmin() || $this->hasValidLicense(),
                'expires_at' => $license?->expires_at?->toIso8601String(),
                // The full key is only visible to master admins; others see a masked version.
                'key' => $this->when(
                    $license !== null,
                    fn () => $viewerIsAdmin
                        ? $license->key
                        : substr($license->key, 0, 9).str_repeat('*', 5).substr($license->key, -5),
                ),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
