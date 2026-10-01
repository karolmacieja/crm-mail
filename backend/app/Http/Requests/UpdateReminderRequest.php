<?php

namespace App\Http\Requests;

class UpdateReminderRequest extends StoreReminderRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->partial(parent::rules());
    }
}
