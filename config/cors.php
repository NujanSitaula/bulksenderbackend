<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This is primarily for local development where the Next.js frontend
    | runs on a different origin than the Laravel API.
    |
    */

    'paths' => ['api/*', 'track/*', 'unsubscribe/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(
        ',',
        (string) env('BULKMAIL_CORS_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000')
    ),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];

