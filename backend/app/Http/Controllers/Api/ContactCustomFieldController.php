<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomFieldRequest;
use App\Http\Resources\ContactCustomFieldResource;
use App\Models\Contact;
use App\Models\ContactCustomField;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Dodaj pole" on the client card, e.g. Alergie: orzechy.
 */
class ContactCustomFieldController extends Controller
{
    public function store(CustomFieldRequest $request, Contact $contact): JsonResponse
    {
        $data = $this->normalize($request->validated());
        $key = Str::slug($data['label'], '_');

        if ($contact->customFields()->where('key', $key)->exists()) {
            throw ValidationException::withMessages(['label' => [__('crm.custom_field_exists', ['label' => $data['label']])]]);
        }

        $field = $contact->customFields()->create($data + [
            'key' => $key,
            'sort_order' => (int) $contact->customFields()->max('sort_order') + 1,
        ]);

        return (new ContactCustomFieldResource($field))->response()->setStatusCode(201);
    }

    public function update(CustomFieldRequest $request, Contact $contact, ContactCustomField $field): ContactCustomFieldResource
    {
        abort_unless($field->contact_id === $contact->id, 404);

        $field->update($this->normalize($request->validated()));

        return new ContactCustomFieldResource($field);
    }

    public function destroy(Contact $contact, ContactCustomField $field): JsonResponse
    {
        abort_unless($field->contact_id === $contact->id, 404);

        $field->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        if (($data['type'] ?? null) === CustomFieldType::Boolean->value && array_key_exists('value', $data) && $data['value'] !== null) {
            $data['value'] = filter_var($data['value'], FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }

        return $data;
    }
}
