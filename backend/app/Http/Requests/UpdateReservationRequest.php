<?php

namespace App\Http\Requests;

class UpdateReservationRequest extends StoreReservationRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->partial(parent::rules());
    }
}
