<?php

namespace App\Http\Requests;

class UpdateTaskRequest extends StoreTaskRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['contact_id'] = array_merge(['sometimes'], array_diff($rules['contact_id'], ['required']));
        $rules['title'] = ['sometimes', 'string', 'max:255'];

        return $rules;
    }
}
