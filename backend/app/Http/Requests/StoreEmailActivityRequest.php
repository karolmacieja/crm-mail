<?php

namespace App\Http\Requests;

class StoreEmailActivityRequest extends TenantRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message_id' => ['required', 'string', 'max:255'],
            'thread_id' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'snippet' => ['nullable', 'string', 'max:2000'],
            'from' => ['nullable', 'string', 'max:255'],
            'direction' => ['sometimes', 'in:in,out'],
            'sent_at' => ['nullable', 'date'],
        ];
    }
}
