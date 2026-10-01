<?php

namespace App\Support\Tenancy;

use LogicException;

/**
 * Thrown when a tenant-owned record would be created without a group_id.
 * Fails loudly instead of silently creating data no restaurant can see.
 */
class MissingGroupContext extends LogicException
{
    public static function forModel(string $model): self
    {
        return new self("Cannot create [{$model}] without a group. Set the tenant via Tenancy::set() or create it as a user that belongs to a group.");
    }
}
