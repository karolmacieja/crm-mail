<?php

namespace App\Http\Requests;

class UpdateContactRequest extends StoreContactRequest
{
    /**
     * Same rules as creation, but every field is optional (PATCH semantics).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['email'] = array_merge(['sometimes'], array_diff($rules['email'], ['required']));

        return $rules;
    }
}
