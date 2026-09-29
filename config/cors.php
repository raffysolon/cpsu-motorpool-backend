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
        'https://cpsumotorpool-admin.netlify.app',  // Production Netlify
        'http://localhost:3000',
        'http://localhost:8080',
        'http://localhost:54018',                    // Flutter web debug port
        'http://localhost:54019',
        'http://127.0.0.1:3000',
        'http://localhost',
    ],

    'allowed_origins_patterns' => [
        // Netlify preview deployments
        '#^https://.*--cpsumotorpool-admin\.netlify\.app$#',
        // Any Vercel deployment
        '#^https://.*\.vercel\.app$#',
        // Flutter localhost any port
        '#^http://localhost:\d+$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,  // Changed to true for credentials support

];
