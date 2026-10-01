<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Models\Concerns\BelongsToGroup;
use Database\Factories\ContactCustomFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Flexible contact attribute, e.g. "Alergie: orzechy".
 */
class ContactCustomField extends Model
{
    use BelongsToGroup;

    /** @use HasFactory<ContactCustomFieldFactory> */
    use HasFactory;

    protected $fillable = [
        'label',
        'key',
        'type',
        'value',
        'sort_order',
    ];

    protected $attributes = [
        'type' => 'text',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ContactCustomField $field) {
            $field->key ??= Str::slug($field->label, '_');
            // Always the contact's group, never the caller's.
            $field->group_id = $field->contact?->group_id ?? $field->group_id;
        });
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** The value converted according to its type. */
    public function typedValue(): string|int|float|bool|Carbon|null
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            CustomFieldType::Number => is_numeric($this->value) ? $this->value + 0 : null,
            CustomFieldType::Boolean => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            CustomFieldType::Date => Carbon::parse($this->value),
            CustomFieldType::Text => $this->value,
        };
    }
}
