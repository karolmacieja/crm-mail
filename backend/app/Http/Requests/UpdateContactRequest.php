<?php

namespace App\Http\Requests;

class UpdateContactRequest extends StoreContactRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->partial(parent::rules());
        unset($rules['custom_fields'], $rules['custom_fields.*.label'], $rules['custom_fields.*.type'], $rules['custom_fields.*.value']);

        return $rules;
    }
}
