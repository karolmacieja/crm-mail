<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;

/**
 * Base for CRM requests: foreign keys must point at rows of the user's own group.
 */
abstract class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function groupId(): int
    {
        return (int) $this->user()->group_id;
    }

    protected function existsInGroup(string $table, string $column = 'id'): Exists
    {
        return (new Exists($table, $column))->where('group_id', $this->groupId());
    }

    /** A contact of the restaurant that this person can open (own or shared). */
    protected function visibleContact(): Exists
    {
        return $this->existsInGroup('contacts')
            ->where(fn ($query) => Contact::constrainVisible($query, (int) $this->user()->id));
    }

    /** A reservation of the restaurant that this person can see. */
    protected function visibleReservation(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if ($value !== null && ! Reservation::query()->whereKey($value)->visibleTo($this->user())->exists()) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        };
    }

    /**
     * Turn "create" rules into PATCH rules: every field optional, but
     * validated with the same constraints when present.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function partial(array $rules): array
    {
        return collect($rules)->map(function ($fieldRules, string $field) {
            if (str_contains($field, '.')) {
                return $fieldRules;
            }

            $fieldRules = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);

            return ['sometimes', ...array_values(array_filter($fieldRules, fn ($r) => $r !== 'required'))];
        })->all();
    }
}
