<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A seat of a license held by one user ("Przydział").
 */
class LicenseAssignment extends Pivot
{
    protected $table = 'license_user';

    public $incrementing = true;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
