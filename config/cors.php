<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'companies/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],  // or ['http://localhost:5174', 'http://127.0.0.1:5174']

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
