<?php

/*
|--------------------------------------------------------------------------
| CORS for GastroFlowx
|--------------------------------------------------------------------------
| Allowed callers:
|  - the Master Admin web panel (WEB_PANEL_URL, e.g. https://app.domena.pl),
|    which sends cookies → supports_credentials = true;
|  - the Gmail extension (chrome-extension://<CHROME_EXTENSION_IDS>), which
|    uses bearer tokens from its service worker;
|  - https://mail.google.com, for direct calls from the content script.
|
| Credentials are allowed for every listed origin, but that does not expose
| the admin session to Gmail: the session cookie is SameSite=Lax (not sent on
| cross-site fetches) and Sanctum only starts sessions for SANCTUM_STATEFUL_DOMAINS.
|
| Locally, when CHROME_EXTENSION_IDS is empty, any extension id is allowed so
| an unpacked build (whose id differs per machine) works. Never in production.
*/

$list = fn (?string $value) => array_values(array_filter(array_map('trim', explode(',', (string) $value))));

$extensionOrigins = array_map(fn (string $id) => "chrome-extension://{$id}", $list(env('CHROME_EXTENSION_IDS')));

$allowAnyExtension = $extensionOrigins === [] && env('APP_ENV', 'production') !== 'production';

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_unique(array_filter([
        rtrim((string) env('WEB_PANEL_URL', 'http://localhost:5173'), '/'),
        'https://mail.google.com',
        ...$extensionOrigins,
        ...$list(env('CORS_EXTRA_ORIGINS')),
    ]))),

    'allowed_origins_patterns' => $allowAnyExtension ? ['#^chrome-extension://[a-p]{32}$#'] : [],

    'allowed_headers' => [
        'Accept', 'Accept-Language', 'Authorization', 'Content-Type',
        'X-Requested-With', 'X-XSRF-TOKEN', 'X-Client-Version',
    ],

    'exposed_headers' => ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Content-Language'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
