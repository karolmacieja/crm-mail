<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldType;
use App\Models\Contact;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContactRequest extends TenantRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }

        // Business correspondence: colleagues keep personal cards of this e-mail,
        // so a new card is a personal one in the same private category.
        if ($this->route('contact') === null && $this->filled('email') && ! Contact::isPrivateCategory($this->categoryId())) {
            $category = Contact::query()->where('email', $this->input('email'))->where('personal_key', '!=', 0)
                ->whereNotNull('category_id')->value('category_id');
            if ($category !== null) {
                $this->merge(['category_id' => $category]);
            }
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
                    ->where('personal_key', $this->personalKey())
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
            'email.unique' => $this->personalKey() !== 0 ? __('crm.personal.already_have_card') : $this->emailTakenMessage(),
            'phone.regex' => __('crm.phone_format'),
        ];
    }

    /**
     * Shared cards (one per e-mail) and personal cards (one per person, private
     * category) of the same e-mail never mix.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $contact = $this->route('contact');
            $email = $this->input('email', $contact?->email);
            if ($validator->errors()->has('email') || ! is_string($email) || $email === '') {
                return;
            }

            $others = fn () => Contact::query()->where('email', $email)->when($contact, fn ($q) => $q->whereKeyNot($contact->id));
            if ($this->personalKey() !== 0 && $others()->where('personal_key', 0)->exists()) {
                $validator->errors()->add('email', $this->emailTakenMessage());
            } elseif ($this->personalKey() === 0 && $others()->where('personal_key', '!=', 0)->exists()) {
                $validator->errors()->add($this->has('category_id') ? 'category_id' : 'email', __('crm.personal.other_cards'));
            }
        }];
    }

    /** Category the card will have (the request's, else the current one). */
    protected function categoryId(): ?int
    {
        $id = $this->has('category_id') ? $this->input('category_id') : $this->route('contact')?->category_id;

        return is_numeric($id) ? (int) $id : null;
    }

    /** 0 for shared cards; the owner's id for personal cards (private category). */
    protected function personalKey(): int
    {
        if (! Contact::isPrivateCategory($this->categoryId())) {
            return 0;
        }

        return (int) ($this->route('contact')?->user_id ?? $this->user()->id);
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
