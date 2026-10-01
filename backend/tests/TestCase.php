<?php

namespace Tests;

use App\Http\Middleware\EnsureClientType;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /** Authenticate like the Gmail extension: personal access token with the "crm" ability. */
    protected function actingAsExtension(User $user): static
    {
        Sanctum::actingAs($user, [EnsureClientType::EXTENSION_ABILITY]);

        return $this;
    }

    /** Authenticate like the web panel: session cookie (Sanctum resolves it via the web guard). */
    protected function actingAsWebPanel(User $user): static
    {
        return $this->actingAs($user, 'web');
    }
}
