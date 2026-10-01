<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldType;
use Illuminate\Validation\Rule;

class CustomFieldRequest extends TenantRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $type = $this->input('type', $this->route('field')?->type?->value ?? CustomFieldType::Text->value);

        // The value is validated according to the field type.
        $valueRule = match ($type) {
            CustomFieldType::Number->value => 'numeric',
            CustomFieldType::Date->value => 'date',
            CustomFieldType::Boolean->value => 'boolean',
            default => 'string',
        };

        return [
            'label' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'type' => ['sometimes', Rule::enum(CustomFieldType::class)],
            // max:2000 means characters for strings, but the numeric value for numbers.
            'value' => ['nullable', $valueRule, ...($valueRule === 'string' ? ['max:2000'] : [])],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
