<?php

namespace App\Http\Resources;

use App\Models\ContactCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContactCategory */
class ContactCategoryResource extends JsonResource
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
            'color' => $this->color,
            'icon' => $this->icon,
            'is_private' => (bool) $this->is_private,
            'sort_order' => $this->sort_order,
            'contacts_count' => $this->whenCounted('contacts'),
        ];
    }
}
