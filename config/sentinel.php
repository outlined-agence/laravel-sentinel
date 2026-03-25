<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Sentinel Monitoring
    |--------------------------------------------------------------------------
    |
    | This option controls whether Sentinel monitoring is enabled. When
    | disabled, no alerts will be sent and no logs will be recorded.
    |
    */
    'enabled' => env('SENTINEL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Async Notifications
    |--------------------------------------------------------------------------
    |
    | When enabled, Slack/Discord notifications are dispatched via queue
    | instead of being sent synchronously. This prevents webhook timeouts
    | from blocking your application requests.
    |
    */
    'async' => [
        'enabled' => env('SENTINEL_ASYNC_ENABLED', false),
        'queue' => env('SENTINEL_ASYNC_QUEUE', null),
        'connection' => env('SENTINEL_ASYNC_CONNECTION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Storage
    |--------------------------------------------------------------------------
    |
    | Enable database storage to persist monitoring events. This is required
    | for the Filament dashboard. Run migrations after enabling.
    |
    */
    'database' => [
        'enabled' => env('SENTINEL_DATABASE_ENABLED', false),
        'connection' => env('SENTINEL_DATABASE_CONNECTION', null),
        'table' => 'sentinel_events',
        'retention_days' => env('SENTINEL_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Slack webhook integration for sending monitoring alerts.
    | Get your webhook URL from Slack's Incoming Webhooks app.
    |
    */
    'slack' => [
        'enabled' => env('SENTINEL_SLACK_ENABLED', true),
        'webhook_url' => env('SENTINEL_SLACK_WEBHOOK'),
        'channel' => env('SENTINEL_SLACK_CHANNEL', '#monitoring'),
        'username' => env('SENTINEL_SLACK_USERNAME', 'Sentinel'),
        'icon_emoji' => env('SENTINEL_SLACK_EMOJI', ':shield:'),
        'mention_user_id' => env('SENTINEL_SLACK_MENTION_ID'),
        'mention_on_levels' => ['critical', 'alert', 'emergency'],
        'timeout' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Discord Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Discord webhook integration for sending monitoring alerts.
    | Get your webhook URL from Discord's channel integrations.
    |
    */
    'discord' => [
        'enabled' => env('SENTINEL_DISCORD_ENABLED', false),
        'webhook_url' => env('SENTINEL_DISCORD_WEBHOOK'),
        'username' => env('SENTINEL_DISCORD_USERNAME', 'Sentinel'),
        'avatar_url' => env('SENTINEL_DISCORD_AVATAR'),
        'mention_role_id' => env('SENTINEL_DISCORD_MENTION_ROLE'),
        'mention_on_levels' => ['critical', 'alert', 'emergency'],
        'timeout' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sentry Integration
    |--------------------------------------------------------------------------
    |
    | When enabled and Sentry is installed, errors and alerts will also
    | be sent to Sentry for additional tracking and analysis.
    |
    */
    'sentry' => [
        'enabled' => env('SENTINEL_SENTRY_ENABLED', true),
        'capture_business_events' => true,
        'capture_threshold_alerts' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Deduplication
    |--------------------------------------------------------------------------
    |
    | Configure deduplication to prevent alert spam. Identical alerts
    | will only be sent once within the configured TTL period.
    |
    */
    'deduplication' => [
        'enabled' => env('SENTINEL_DEDUP_ENABLED', true),
        'ttl' => env('SENTINEL_DEDUP_TTL', 3600), // 1 hour default
        'cache_store' => env('SENTINEL_DEDUP_CACHE', null), // null = default cache
        'cache_prefix' => 'sentinel_alert_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Global rate limiting for notifications to prevent excessive alerts.
    | Limits apply per channel (Slack, Discord) independently.
    |
    */
    'rate_limit' => [
        'enabled' => env('SENTINEL_RATE_LIMIT_ENABLED', true),
        'max_per_minute' => env('SENTINEL_RATE_LIMIT_PER_MINUTE', 30),
        'max_per_hour' => env('SENTINEL_RATE_LIMIT_PER_HOUR', 200),
        'cache_store' => env('SENTINEL_RATE_LIMIT_CACHE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Filtering
    |--------------------------------------------------------------------------
    |
    | Configure which exceptions should be ignored by the monitoring system.
    | These exceptions will not trigger alerts or be logged.
    |
    */
    'ignore_exceptions' => [
        Illuminate\Validation\ValidationException::class,
        Illuminate\Auth\AuthenticationException::class,
        Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        Illuminate\Session\TokenMismatchException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Status Code Filtering
    |--------------------------------------------------------------------------
    |
    | HTTP exceptions with these status codes will be ignored.
    | By default, client errors (4xx except specific ones) are ignored.
    |
    */
    'ignore_http_codes' => [
        400, 401, 403, 404, 405, 408, 419, 422, 429,
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Extractors
    |--------------------------------------------------------------------------
    |
    | Register custom context extractors to add additional context to
    | monitoring events. Each class must implement ContextExtractor.
    |
    */
    'context_extractors' => [
        // \App\Monitoring\CustomContextExtractor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Levels per Event Type
    |--------------------------------------------------------------------------
    |
    | Configure the log level used for each type of monitoring event.
    |
    */
    'levels' => [
        'error' => 'error',
        'business_error' => 'error',
        'business_success' => 'info',
        'provider_error' => 'error',
        'threshold_warning' => 'warning',
        'threshold_critical' => 'critical',
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitored Resources
    |--------------------------------------------------------------------------
    |
    | Register resources to monitor. Each class must implement MonitorableResource.
    | Resources are checked by the sentinel:check-resources command.
    |
    */
    'resources' => [
        // \App\Monitoring\Resources\StripeBalanceResource::class,
        // \App\Monitoring\Resources\DiskSpaceResource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Check Schedule
    |--------------------------------------------------------------------------
    |
    | How often to check monitored resources (in minutes).
    |
    */
    'resource_check_interval' => env('SENTINEL_RESOURCE_CHECK_INTERVAL', 5),

    /*
    |--------------------------------------------------------------------------
    | Metrics (Prometheus/StatsD)
    |--------------------------------------------------------------------------
    |
    | Configure metrics collection for monitoring dashboards like Grafana.
    |
    */
    'metrics' => [
        'enabled' => env('SENTINEL_METRICS_ENABLED', false),
        'driver' => env('SENTINEL_METRICS_DRIVER', 'prometheus'), // prometheus, statsd
        'prefix' => env('SENTINEL_METRICS_PREFIX', 'sentinel'),

        'prometheus' => [
            'namespace' => env('SENTINEL_PROMETHEUS_NAMESPACE', 'app'),
            'storage' => env('SENTINEL_PROMETHEUS_STORAGE', 'in_memory'), // in_memory, redis, apc
            'redis_connection' => env('SENTINEL_PROMETHEUS_REDIS', 'default'),
        ],

        'statsd' => [
            'host' => env('SENTINEL_STATSD_HOST', '127.0.0.1'),
            'port' => env('SENTINEL_STATSD_PORT', 8125),
            'protocol' => env('SENTINEL_STATSD_PROTOCOL', 'udp'), // udp, tcp
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Sanitization
    |--------------------------------------------------------------------------
    |
    | Mask sensitive data before sending context to external webhooks
    | (Slack, Discord). Database storage keeps the original context.
    |
    */
    'sanitization' => [
        'enabled' => env('SENTINEL_SANITIZATION_ENABLED', true),
        'mask' => '********',
        'fields' => [
            'password',
            'password_confirmation',
            'secret',
            'token',
            'api_key',
            'api_secret',
            'access_token',
            'refresh_token',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
            'authorization',
        ],
        'patterns' => [
            // Add custom regex patterns to mask, e.g.:
            // '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatting
    |--------------------------------------------------------------------------
    |
    | Customize the appearance of alerts in Slack and Discord.
    |
    */
    'formatting' => [
        'colors' => [
            'debug' => '#808080',     // Gray
            'info' => '#3498db',      // Blue
            'notice' => '#2ecc71',    // Green
            'warning' => '#f39c12',   // Orange
            'error' => '#e74c3c',     // Red
            'critical' => '#9b59b6',  // Purple
            'alert' => '#e91e63',     // Pink
            'emergency' => '#000000', // Black
        ],
        'emojis' => [
            'debug' => ':mag:',
            'info' => ':information_source:',
            'notice' => ':white_check_mark:',
            'warning' => ':warning:',
            'error' => ':x:',
            'critical' => ':fire:',
            'alert' => ':rotating_light:',
            'emergency' => ':skull:',
        ],
        'include_stack_trace' => true,
        'stack_trace_lines' => 10,
        'max_context_depth' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Dashboard
    |--------------------------------------------------------------------------
    |
    | Configure the Filament dashboard integration for viewing monitoring events.
    |
    */
    'filament' => [
        'enabled' => env('SENTINEL_FILAMENT_ENABLED', true),
        'navigation_group' => 'Monitoring',
        'navigation_icon' => 'heroicon-o-shield-check',
        'navigation_sort' => 100,
    ],
];
