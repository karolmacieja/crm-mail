<?php

namespace App\Http\Requests;

use App\Enums\ReminderType;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends TenantRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => [Rule::enum(ReminderType::class)],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'remind_at' => ['required', 'date'],
            'is_done' => ['boolean'],
            'contact_id' => ['nullable', 'integer', $this->visibleContact()],
            'reservation_id' => ['nullable', 'integer', $this->existsInGroup('reservations'), $this->visibleReservation()],
            'source_email_id' => ['nullable', 'string', 'max:255'],
            'source_email_subject' => ['nullable', 'string', 'max:255'],
        ];
    }
}
