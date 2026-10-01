<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    private function preflight(string $origin)
    {
        return $this->call('OPTIONS', '/api/contacts', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,x-xsrf-token',
        ]);
    }

    public function test_web_panel_origin_may_send_credentials(): void
    {
        $this->preflight('http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_extension_and_gmail_origins_are_allowed(): void
    {
        $this->preflight('chrome-extension://abcdefghijklmnopabcdefghijklmnop')
            ->assertHeader('Access-Control-Allow-Origin', 'chrome-extension://abcdefghijklmnopabcdefghijklmnop');
        $this->preflight('https://mail.google.com')
            ->assertHeader('Access-Control-Allow-Origin', 'https://mail.google.com');
    }

    public function test_unknown_origin_is_not_allowed(): void
    {
        $this->preflight('https://evil.example')->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
