<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The Next.js frontend runs on a different domain (e.g., Vercel), so the API must allow
    | its origin. Set CORS_ALLOWED_ORIGINS to a comma-separated list on public servers;
    | it defaults to every origin for local development.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Lets the browser read the file name of downloaded MOVs and CSV exports
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    // Authentication uses bearer tokens, not cookies
    'supports_credentials' => false,

];
