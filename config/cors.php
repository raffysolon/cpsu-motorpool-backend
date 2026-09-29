<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://cpsumotorpool-admin.netlify.app',  // Production admin web app
        'http://localhost:3000',                     // Local development (React/Vue)
        'http://localhost:8080',                     // Local development (Vue alternative port)
        'http://127.0.0.1:3000',                     // Local development (alternative)
        'http://localhost',                          // Local testing
    ],

    'allowed_origins_patterns' => [
        // Allow Netlify preview deployments: https://deploy-preview-123--cpsumotorpool-admin.netlify.app
        '#^https://.*--cpsumotorpool-admin\.netlify\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,  // Changed to true for credentials support

];
