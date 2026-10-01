<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends TenantRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Optional: internal tasks ("Rozesłać grafik kelnerów") have no contact.
            'contact_id' => ['nullable', 'integer', $this->existsInGroup('contacts')],
            'assigned_to' => ['nullable', 'integer', $this->existsInGroup('users')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => [Rule::enum(TaskType::class)],
            'priority' => [Rule::enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date'],
            'is_completed' => ['boolean'],
            'source_email_id' => ['nullable', 'string', 'max:255'],
            'source_email_subject' => ['nullable', 'string', 'max:255'],
        ];
    }
}
