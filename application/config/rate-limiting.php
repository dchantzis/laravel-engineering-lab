<?php

return [
    'enabled' => (bool) env('RATE_LIMIT_ENABLED', true),

    'web' => [
        'default' => (int) env('RATE_LIMIT_WEB', 60),
    ],

    'api' => [
        'default' => (int) env('RATE_LIMIT_API_DEFAULT', 120),
        'admin' => (int) env('RATE_LIMIT_API_ADMIN', 120),
        'client_credentials' => (int) env('RATE_LIMIT_API_CLIENT_CREDENTIALS', 300),
    ],

    'pre_auth' => [
        'default' => (int) env('RATE_LIMIT_PRE_AUTH', 120),
    ],
];
