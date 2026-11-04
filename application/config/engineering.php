<?php

return [
    'sensitive_data' => [
        'fields' => [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'client_secret',
            'authorization',
            'secret',
        ],
    ],

    'system_logs' => [
        'sanitize_sensitive_data' => env('SYSTEM_LOGS_SANITIZE_SENSITIVE_DATA', true),
    ],
];
