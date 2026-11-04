<?php

return [

    'rate_limit' => [
        'api' => [
            'enabled' => env('API_RATE_LIMIT_ENABLED', false),
            'default' => (int) env('API_RATE_LIMIT_DEFAULT', 150),
            'admin' => (int) env('API_RATE_LIMIT_ADMIN', 100),
        ],
        'web' => [
            'default' => (int) env('WEB_RATE_LIMIT', 60),
        ],
    ],

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
        'sanitize_sensitive_data' => env(
            'SYSTEM_LOGS_SANITIZE_SENSITIVE_DATA',
            true
        ),
    ],

    'sanitise_sensitive_data_in_admin_responses' => (bool) env('SANITIZE_SENSITIVE_DATA_IN_ADMIN_RESPONSES', false),
    'console_command_execution_heartbeat_interval_seconds' => (int) env('CONSOLE_COMMAND_EXECUTION_HEARTBEAT_INTERVAL_SECONDS', 30),
    'scheduler_commands_scan_orphan_threshold_minutes' => (int) env('SCHEDULER_COMMANDS_SCAN_ORPHAN_THRESHOLD_MINUTES', 5),
    'console_scheduled_command_quiet_run_enabled' => env('APP_SCHEDULED_COMMAND_QUIET_RUN_ENABLED', false),
    'log_all_api_responses' => env('LOG_ALL_API_RESPONSES', false),
    'api_audit_logs_enabled' => env('API_AUDIT_LOGS_ENABLED', false),
    'system_log_num_days_purge' => (int) env('SYSTEM_LOG_NUM_DAYS_PURGE', 120),
    'console_command_log_num_days_purge' => (int) env('CONSOLE_COMMAND_LOG_NUM_DAYS_PURGE', 120),
    'api_audit_log_num_days_purge' => (int) env('API_AUDIT_LOG_NUM_DAYS_PURGE', 120),
    'ip_trace_log_num_days_purge' => (int) env('IP_TRACE_LOG_NUM_DAYS_PURGE', 120),
    'log_slack_notifications_enabled' => env('LOG_SLACK_NOTIFICATIONS_ENABLED', true),
    'log_teams_notifications_enabled' => env('LOG_TEAMS_NOTIFICATIONS_ENABLED', false),
    'notification_channel' => env('NOTIFICATION_CHANNEL', 'slack'), // Options: slack, teams, both
    'log_teams_notifications_webhook_timeout_seconds' => (int) env('LOG_TEAMS_NOTIFICATIONS_WEBHOOK_TIMEOUT_SECONDS', 10),
];
