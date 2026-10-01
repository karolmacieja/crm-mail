<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The contact must exist AND belong to the user's group (restaurant).
            'contact_id' => [
                'required', 'integer',
                Rule::exists('contacts', 'id')->where('group_id', $this->user()->group_id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'is_completed' => ['sometimes', 'boolean'],
        ];
    }
}
