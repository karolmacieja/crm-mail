<?php

namespace Tests\Feature;

use Tests\TestCase;

class RootRedirectTest extends TestCase
{
    public function test_api_root_redirects_to_the_web_panel(): void
    {
        $this->get('/')->assertRedirect(config('app.web_panel_url'));
    }
}
