<?php

/*
|--------------------------------------------------------------------------
| CORS for the Gmail CRM extension
|--------------------------------------------------------------------------
| The extension routes API calls through its MV3 service worker (origin
| chrome-extension://<id>), and may also call directly from Gmail
| (origin https://mail.google.com). Authentication uses bearer tokens,
| never cookies, so credentials support stays disabled.
|
| Lock the extension origin down in production:
|   CORS_ALLOWED_ORIGINS="https://mail.google.com,chrome-extension://<your-extension-id>"
*/

$origins = array_values(array_filter(array_map('trim', explode(',', (string) env(
    'CORS_ALLOWED_ORIGINS',
    'https://mail.google.com'
)))));

$patterns = array_values(array_filter(array_map('trim', explode(',', (string) env(
    'CORS_ALLOWED_ORIGIN_PATTERNS',
    // Any Chrome extension id (32 chars a-p). Override with a fixed origin in production.
    '#^chrome-extension://[a-p]{32}$#'
)))));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => $patterns,

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-Client-Version'],

    'exposed_headers' => ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'],

    'max_age' => 86400,

    'supports_credentials' => false,

];
