<?php

namespace App\Http\Resources;

use App\Models\ContactCustomField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContactCustomField */
class ContactCustomFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $typed = $this->typedValue();

        return [
            'id' => $this->id,
            'label' => $this->label,
            'key' => $this->key,
            'type' => $this->type->value,
            'value' => $typed instanceof \DateTimeInterface ? $typed->format('Y-m-d') : $typed,
            'sort_order' => $this->sort_order,
        ];
    }
}
