<?php

namespace App\Http\Requests;

use App\Enums\ReservationStatus;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends TenantRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['nullable', 'integer', $this->visibleContact()],
            // Restaurant-local date and time, exactly as the guest asked.
            'reservation_date' => ['required', 'date_format:Y-m-d'],
            'reservation_time' => ['required', 'date_format:H:i'],
            'guests_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'status' => [Rule::enum(ReservationStatus::class)],
            'table_label' => ['nullable', 'string', 'max:50'],
            'occasion' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'source_email_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
