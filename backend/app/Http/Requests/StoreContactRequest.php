<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldType;
use App\Models\Contact;
use Illuminate\Validation\Rule;

class StoreContactRequest extends TenantRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('contacts', 'email')
                    ->where('group_id', $this->groupId())
                    ->ignore($this->route('contact')),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+().\-\s\/x]*$/i'],
            'status' => ['string', $this->existsInGroup('contact_statuses', 'key')],
            'is_client' => ['boolean'],
            'category_id' => ['nullable', 'integer', $this->existsInGroup('contact_categories')],
            'notes' => ['nullable', 'string', 'max:10000'],
            // Optional initial custom fields, e.g. [{"label": "Alergie", "value": "orzechy"}]
            'custom_fields' => ['sometimes', 'array', 'max:50'],
            'custom_fields.*.label' => ['required', 'string', 'max:100'],
            'custom_fields.*.type' => [Rule::enum(CustomFieldType::class)],
            'custom_fields.*.value' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => $this->emailTakenMessage(),
            'phone.regex' => __('crm.phone_format'),
        ];
    }

    /** One card per e-mail: if a colleague looks after this client, say who. */
    private function emailTakenMessage(): string
    {
        $owner = Contact::query()->with('owner:id,name')->where('email', (string) $this->input('email'))->first()?->owner;

        return $owner !== null && $owner->id !== $this->user()->id
            ? __('crm.sharing.email_owned_by', ['name' => $owner->name])
            : __('crm.contact_email_taken');
    }
}
