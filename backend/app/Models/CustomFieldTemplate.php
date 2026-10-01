<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Models\Concerns\BelongsToGroup;
use Illuminate\Database\Eloquent\Model;

/** Suggested custom field ("Alergie", "NIP") offered under "Dodaj pole". */
class CustomFieldTemplate extends Model
{
    use BelongsToGroup;

    protected $fillable = ['label', 'type', 'sort_order'];

    protected function casts(): array
    {
        return ['type' => CustomFieldType::class, 'sort_order' => 'integer'];
    }
}
