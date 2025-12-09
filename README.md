# Laravel Sentinel

A comprehensive Laravel package for centralized application monitoring with Slack/Discord notifications, exception tracking, resource monitoring, and metrics collection.

## Features

- **Centralized Monitoring Service** - Injectable service for logging errors, business events, provider errors, and threshold alerts
- **Slack & Discord Notifications** - Beautiful, formatted messages with colors, emojis, and attachments
- **Alert Deduplication** - Prevent alert spam by suppressing duplicate notifications
- **Rate Limiting** - Global rate limiting for notifications per channel
- **Exception Filtering** - Automatically filter common exceptions (404s, validation errors, etc.)
- **Sentry Integration** - Optional integration with Sentry for additional error tracking
- **Resource Monitoring** - Extensible system for monitoring external resources with threshold alerts
- **Prometheus/StatsD Metrics** - Export metrics for monitoring dashboards
- **Filament Dashboard** - Beautiful admin panel for viewing monitoring events
- **Database Storage** - Optional persistence of monitoring events

## Requirements

- PHP 8.1+
- Laravel 10.0+ or 11.0+

## Installation

Install via Composer:

```bash
composer require outlined/laravel-sentinel
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=sentinel-config
```

(Optional) Publish and run migrations for database storage:

```bash
php artisan vendor:publish --tag=sentinel-migrations
php artisan migrate
```

## Configuration

Add the following to your `.env` file:

```env
# Enable/disable Sentinel
SENTINEL_ENABLED=true

# Slack configuration
SENTINEL_SLACK_ENABLED=true
SENTINEL_SLACK_WEBHOOK=https://hooks.slack.com/services/...
SENTINEL_SLACK_CHANNEL=#monitoring
SENTINEL_SLACK_MENTION_ID=U12345678

# Discord configuration (optional)
SENTINEL_DISCORD_ENABLED=false
SENTINEL_DISCORD_WEBHOOK=https://discord.com/api/webhooks/...

# Sentry integration
SENTINEL_SENTRY_ENABLED=true

# Database storage
SENTINEL_DATABASE_ENABLED=true
SENTINEL_RETENTION_DAYS=30

# Deduplication
SENTINEL_DEDUP_ENABLED=true
SENTINEL_DEDUP_TTL=3600

# Rate limiting
SENTINEL_RATE_LIMIT_ENABLED=true
SENTINEL_RATE_LIMIT_PER_MINUTE=30
SENTINEL_RATE_LIMIT_PER_HOUR=200

# Metrics (Prometheus/StatsD)
SENTINEL_METRICS_ENABLED=false
SENTINEL_METRICS_DRIVER=prometheus
```

## Usage

### Via Facade

```php
use Outlined\Sentinel\Facades\Sentinel;

// Log an exception
Sentinel::logError($exception);
Sentinel::logError($exception, auth()->user(), ['order_id' => $order->id]);

// Log business events
Sentinel::logBusinessEvent('payment', false, 'Payment failed', [
    'order_id' => 123,
    'amount' => 99.99,
    'reason' => 'Card declined',
]);

Sentinel::logBusinessEvent('registration', true, 'New user registered', [
    'user_id' => $user->id,
    'source' => 'organic',
]);

// Log provider errors
Sentinel::logProviderError('Stripe', 'Card declined', [
    'customer_id' => 456,
    'error_code' => 'card_declined',
]);

// Log threshold alerts
Sentinel::logThresholdAlert(
    type: 'balance',
    message: 'Account balance is low',
    currentValue: 50.00,
    threshold: 100.00,
    critical: false
);
```

### Via Dependency Injection

```php
use Outlined\Sentinel\Services\MonitoringService;

class PaymentController
{
    public function __construct(
        private MonitoringService $monitoring
    ) {}

    public function process(Request $request)
    {
        try {
            // Process payment...
        } catch (PaymentException $e) {
            $this->monitoring->logError($e, auth()->user(), [
                'order_id' => $request->order_id,
            ]);
            throw $e;
        }
    }
}
```

### Temporarily Disable Monitoring

```php
// Disable/enable manually
Sentinel::disable();
Sentinel::enable();

// Execute code without monitoring
$result = Sentinel::withoutMonitoring(function () {
    // This code won't trigger any monitoring alerts
    return performSensitiveOperation();
});
```

## Exception Handling

### Option 1: Use the Sentinel Exception Handler

Replace your exception handler in `bootstrap/app.php` (Laravel 11):

```php
use Outlined\Sentinel\Exceptions\SentinelExceptionHandler;

return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->handler(SentinelExceptionHandler::class);
    })
    ->create();
```

### Option 2: Use the Trait

Add the trait to your existing exception handler:

```php
use Outlined\Sentinel\Exceptions\ReportsToSentinel;

class Handler extends ExceptionHandler
{
    use ReportsToSentinel;

    public function report(Throwable $e): void
    {
        $this->reportToSentinelIfNeeded($e);
        parent::report($e);
    }
}
```

### Ignored Exceptions

By default, these exceptions are ignored:

- `ValidationException`
- `AuthenticationException`
- `NotFoundHttpException` (404)
- `MethodNotAllowedHttpException`
- `TokenMismatchException`

Customize in `config/sentinel.php`:

```php
'ignore_exceptions' => [
    \Illuminate\Validation\ValidationException::class,
    \App\Exceptions\CustomIgnoredException::class,
],

'ignore_http_codes' => [
    400, 401, 403, 404, 405, 408, 419, 422, 429,
],
```

## Resource Monitoring

Create custom resources to monitor:

```php
use Outlined\Sentinel\Contracts\MonitorableResource;
use Outlined\Sentinel\Resources\AbstractResource;
use Outlined\Sentinel\Resources\ResourceStatus;

class StripeBalanceResource extends AbstractResource
{
    protected float $warningThreshold = 1000;
    protected float $criticalThreshold = 100;
    protected bool $higherIsBetter = true;

    public function getIdentifier(): string
    {
        return 'stripe_balance';
    }

    public function getName(): string
    {
        return 'Stripe Balance';
    }

    public function check(): ResourceStatus
    {
        try {
            $balance = \Stripe\Balance::retrieve();
            $available = $balance->available[0]->amount / 100;

            return $this->healthy($available, "Balance: \${$available}");
        } catch (\Exception $e) {
            return $this->failed("Failed to check balance: {$e->getMessage()}");
        }
    }
}
```

Register in `config/sentinel.php`:

```php
'resources' => [
    \App\Monitoring\Resources\StripeBalanceResource::class,
    \App\Monitoring\Resources\DiskSpaceResource::class,
],
```

Check resources manually or via CRON:

```bash
# Check all resources
php artisan sentinel:check-resources

# Check specific resource
php artisan sentinel:check-resources --resource=stripe_balance

# Check without alerting
php artisan sentinel:check-resources --no-alert

# JSON output
php artisan sentinel:check-resources --json
```

Schedule in `routes/console.php`:

```php
Schedule::command('sentinel:check-resources')->everyFiveMinutes();
```

## Context Extractors

Add custom context to monitoring events:

```php
use Outlined\Sentinel\Contracts\ContextExtractor;

class TenantContextExtractor implements ContextExtractor
{
    public function shouldExtract(string $eventType, array $context, ?Throwable $exception = null): bool
    {
        return true; // Always extract
    }

    public function extract(string $eventType, array $context, ?Throwable $exception = null): array
    {
        return [
            'tenant' => [
                'id' => tenant()?->id,
                'name' => tenant()?->name,
            ],
        ];
    }

    public function priority(): int
    {
        return 100;
    }
}
```

Register in `config/sentinel.php`:

```php
'context_extractors' => [
    \App\Monitoring\TenantContextExtractor::class,
],
```

## Filament Dashboard

If you have Filament installed, register the plugin:

```php
use Outlined\Sentinel\Filament\SentinelPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            SentinelPlugin::make(),
        ]);
}
```

Customize in `config/sentinel.php`:

```php
'filament' => [
    'enabled' => true,
    'navigation_group' => 'Monitoring',
    'navigation_icon' => 'heroicon-o-shield-check',
    'navigation_sort' => 100,
],
```

## Metrics

### Prometheus

Install the Prometheus client:

```bash
composer require promphp/prometheus_client_php
```

Configure in `.env`:

```env
SENTINEL_METRICS_ENABLED=true
SENTINEL_METRICS_DRIVER=prometheus
SENTINEL_PROMETHEUS_STORAGE=redis
```

Expose metrics endpoint:

```php
// routes/web.php
use Outlined\Sentinel\Contracts\MetricsCollector;

Route::get('/metrics', function (MetricsCollector $metrics) {
    return response($metrics->render(), 200, [
        'Content-Type' => 'text/plain; charset=utf-8',
    ]);
});
```

### StatsD

```env
SENTINEL_METRICS_ENABLED=true
SENTINEL_METRICS_DRIVER=statsd
SENTINEL_STATSD_HOST=127.0.0.1
SENTINEL_STATSD_PORT=8125
```

## Artisan Commands

```bash
# Send a test notification
php artisan sentinel:test
php artisan sentinel:test --level=error --message="Test error"

# Check monitored resources
php artisan sentinel:check-resources

# Prune old events
php artisan sentinel:prune
php artisan sentinel:prune --days=7
php artisan sentinel:prune --dry-run
```

## Message Formatting

Customize colors and emojis in `config/sentinel.php`:

```php
'formatting' => [
    'colors' => [
        'debug' => '#808080',
        'info' => '#3498db',
        'warning' => '#f39c12',
        'error' => '#e74c3c',
        'critical' => '#9b59b6',
    ],
    'emojis' => [
        'debug' => ':mag:',
        'info' => ':information_source:',
        'warning' => ':warning:',
        'error' => ':x:',
        'critical' => ':fire:',
    ],
    'include_stack_trace' => true,
    'stack_trace_lines' => 10,
],
```

## Events

Listen to monitoring events:

```php
use Outlined\Sentinel\Events\MonitoringEventLogged;

Event::listen(MonitoringEventLogged::class, function ($event) {
    // $event->level
    // $event->message
    // $event->context
});
```

## Testing

```bash
composer test
composer test-coverage
composer analyse
```

## License

MIT License. See [LICENSE](LICENSE) for more information.
