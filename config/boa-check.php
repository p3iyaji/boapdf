<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed environments
    |--------------------------------------------------------------------------
    |
    | Boa Check registers routes only when APP_ENV is in this list.
    | Default: local only (FR-3).
    |
    */

    'allowed_environments' => [
        'local',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route prefix
    |--------------------------------------------------------------------------
    */

    'route_prefix' => 'boa-check',

    /*
    |--------------------------------------------------------------------------
    | Read-only mode (FR-6)
    |--------------------------------------------------------------------------
    |
    | When true, write and execute operations are rejected by OperationPolicy.
    |
    */

    'read_only' => (bool) env('BOA_CHECK_READ_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Execution limits (FR-7, FR-8)
    |--------------------------------------------------------------------------
    */

    'max_execution_seconds' => (int) env('BOA_CHECK_MAX_EXECUTION_SECONDS', 30),

    'max_rows' => (int) env('BOA_CHECK_MAX_ROWS', 100),

    /*
    |--------------------------------------------------------------------------
    | Transaction mode default (FR-43)
    |--------------------------------------------------------------------------
    */

    'transaction_mode_default' => (bool) env('BOA_CHECK_TRANSACTION_MODE_DEFAULT', false),

    /*
    |--------------------------------------------------------------------------
    | IP whitelist (FR-4)
    |--------------------------------------------------------------------------
    |
    | When empty, all IPs are allowed. When set, only matching IPs or CIDR
    | ranges may access the dashboard (e.g. 127.0.0.1, 10.0.0.0/8).
    |
    */

    'ip_whitelist' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('BOA_CHECK_IP_WHITELIST', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Authentication (FR-5)
    |--------------------------------------------------------------------------
    */

    'auth' => [
        'enabled' => (bool) env('BOA_CHECK_AUTH_ENABLED', true),
        'guard' => env('BOA_CHECK_AUTH_GUARD'),
        'ability' => 'boa-check.access',
        'unauthorized_response' => env('BOA_CHECK_UNAUTHORIZED_RESPONSE', 'forbidden'),
        'login_route' => env('BOA_CHECK_LOGIN_ROUTE', 'login'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard testers (MVP)
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Discovery (FR-9, FR-13, FR-19, FR-23)
    |--------------------------------------------------------------------------
    */

    'discovery' => [
        'cache_ttl' => (int) env('BOA_CHECK_DISCOVERY_CACHE_TTL', 3600),
        'list_limit_default' => (int) env('BOA_CHECK_DISCOVERY_LIST_LIMIT_DEFAULT', 50),
        'list_limit_max' => (int) env('BOA_CHECK_DISCOVERY_LIST_LIMIT_MAX', 500),
        'event_paths' => [
            function_exists('app_path') ? app_path('Events') : '',
        ],
        'event_service_provider' => 'App\\Providers\\EventServiceProvider',
        'model_paths' => [
            function_exists('app_path') ? app_path('Models') : '',
        ],
        'service_paths' => [
            function_exists('app_path') ? app_path('Services') : '',
        ],
        'form_request_paths' => [
            function_exists('app_path') ? app_path('Http/Requests') : '',
        ],
        'resource_paths' => [
            function_exists('app_path') ? app_path('Http/Resources') : '',
        ],
        'job_paths' => [
            function_exists('app_path') ? app_path('Jobs') : '',
        ],
        'trait_paths' => [
            function_exists('app_path') ? app_path('Traits') : '',
        ],
        'seeder_paths' => [
            function_exists('database_path') ? database_path('seeders') : '',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log viewer (FR-47)
    |--------------------------------------------------------------------------
    */

    'log_viewer' => [
        'channel' => env('BOA_CHECK_LOG_VIEWER_CHANNEL'),
        'max_lines' => (int) env('BOA_CHECK_LOG_MAX_LINES', 500),
        'max_bytes' => (int) env('BOA_CHECK_LOG_MAX_BYTES', 524288),
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed jobs viewer (FR-38)
    |--------------------------------------------------------------------------
    */

    'failed_jobs' => [
        'limit' => (int) env('BOA_CHECK_FAILED_JOBS_LIMIT', 50),
        'limit_max' => (int) env('BOA_CHECK_FAILED_JOBS_LIMIT_MAX', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Code runner / Web Tinker (FR-39–FR-41)
    |--------------------------------------------------------------------------
    |
    | Highest-risk surface. Disabled by default (D12). AST allowlist applies.
    |
    */

    'code_runner' => [
        'enabled' => (bool) env('BOA_CHECK_CODE_RUNNER_ENABLED', false),
        'repl_ttl' => (int) env('BOA_CHECK_CODE_RUNNER_REPL_TTL', 3600),
    ],

    'testers' => [
        ['slug' => 'events', 'label' => 'Event Tester', 'route' => null],
        ['slug' => 'models', 'label' => 'Model Tester', 'route' => null],
        ['slug' => 'routes', 'label' => 'Route & Controller Tester', 'route' => null],
        ['slug' => 'form-requests', 'label' => 'FormRequest Tester', 'route' => null],
        ['slug' => 'services', 'label' => 'Service Tester', 'route' => null],
        ['slug' => 'traits', 'label' => 'Trait Tester', 'route' => null],
        ['slug' => 'api', 'label' => 'API & Resource Tester', 'route' => null],
        ['slug' => 'json-compare', 'label' => 'JSON Compare', 'route' => null],
        ['slug' => 'jobs', 'label' => 'Job Tester', 'route' => null],
        ['slug' => 'failed-jobs', 'label' => 'Failed Jobs', 'route' => null],
        ['slug' => 'seeders', 'label' => 'Seeder Tester', 'route' => null],
        ['slug' => 'cache', 'label' => 'Cache Tester', 'route' => null],
        ['slug' => 'session', 'label' => 'Session Inspector', 'route' => null],
        ['slug' => 'code-runner', 'label' => 'Code Runner', 'route' => null],
        ['slug' => 'tinker', 'label' => 'Web Tinker', 'route' => null],
        ['slug' => 'routes-list', 'label' => 'Route List', 'route' => null],
        ['slug' => 'config', 'label' => 'Config Inspector', 'route' => null],
        ['slug' => 'logs', 'label' => 'Log Viewer', 'route' => null],
    ],

];
