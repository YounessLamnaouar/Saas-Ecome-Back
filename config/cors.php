<?php

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Comma-separated list in .env, e.g. http://localhost:5173,https://yourstore.com
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // false: we use Bearer tokens, not cookies, so credentials aren't needed.
    'supports_credentials' => false,
];
