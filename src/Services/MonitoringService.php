<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Outlined\Sentinel\Contracts\MetricsCollector;
use Outlined\Sentinel\Events\MonitoringEventLogged;
use Outlined\Sentinel\Jobs\SendWebhookNotification;
use Outlined\Sentinel\Models\SentinelEvent;
use Outlined\Sentinel\Support\ContextBuilder;
use Outlined\Sentinel\Support\ContextSanitizer;
use Throwable;

class MonitoringService
{
    protected bool $enabled;

    protected ContextBuilder $contextBuilder;

    protected ContextSanitizer $sanitizer;

    protected ?MetricsCollector $metrics = null;

    protected bool $databaseEnabled;

    public function __construct(ContextBuilder $contextBuilder, ?ContextSanitizer $sanitizer = null)
    {
        $this->enabled = (bool) config('sentinel.enabled', true);
        $this->databaseEnabled = (bool) config('sentinel.database.enabled', false);
        $this->contextBuilder = $contextBuilder;
        $this->sanitizer = $sanitizer ?? new ContextSanitizer();

        // Register custom context extractors
        $extractors = config('sentinel.context_extractors', []);
        if (! empty($extractors)) {
            $this->contextBuilder->registerExtractors($extractors);
        }
    }

    /**
     * Set the metrics collector instance.
     */
    public function setMetricsCollector(MetricsCollector $metrics): void
    {
        $this->metrics = $metrics;
    }

    /**
     * Log an exception with rich context.
     *
     * @param  array<string, mixed>  $additionalContext
     */
    public function logError(
        Throwable $exception,
        ?Model $user = null,
        array $additionalContext = [],
    ): void {
        if (! $this->enabled) {
            return;
        }

        $level = config('sentinel.levels.error', 'error');
        $context = $this->contextBuilder->build(
            eventType: 'error',
            additionalContext: $additionalContext,
            exception: $exception,
            user: $user,
        );

        $this->log($level, $exception->getMessage(), $context);
        $this->captureToSentry($exception);
        $this->recordMetric('error', ['exception' => get_class($exception)]);
    }

    /**
     * Log a business event (e.g., payment, code request).
     *
     * @param  array<string, mixed>  $additionalContext
     */
    public function logBusinessEvent(
        string $type,
        bool $success,
        string $message,
        array $additionalContext = [],
    ): void {
        if (! $this->enabled) {
            return;
        }

        $levelKey = $success ? 'business_success' : 'business_error';
        $level = config("sentinel.levels.{$levelKey}", $success ? 'info' : 'error');

        $additionalContext['business_type'] = $type;
        $additionalContext['success'] = $success;

        $context = $this->contextBuilder->build(
            eventType: "business_{$type}",
            additionalContext: $additionalContext,
        );

        $this->log($level, $message, $context);

        if (! $success && config('sentinel.sentry.capture_business_events', true)) {
            $this->captureMessageToSentry($message, $level);
        }

        $this->recordMetric('business_event', [
            'type' => $type,
            'success' => $success ? 'true' : 'false',
        ]);
    }

    /**
     * Log a third-party API/provider error.
     *
     * @param  array<string, mixed>  $data
     */
    public function logProviderError(
        string $provider,
        string $message,
        array $data = [],
    ): void {
        if (! $this->enabled) {
            return;
        }

        $level = config('sentinel.levels.provider_error', 'error');

        $data['provider'] = $provider;
        $context = $this->contextBuilder->build(
            eventType: "provider_error_{$provider}",
            additionalContext: $data,
        );

        $this->log($level, "[{$provider}] {$message}", $context);
        $this->captureMessageToSentry("[{$provider}] {$message}", $level);
        $this->recordMetric('provider_error', ['provider' => $provider]);
    }

    /**
     * Log a threshold alert (e.g., low balance, high error rate).
     */
    public function logThresholdAlert(
        string $type,
        string $message,
        float $currentValue,
        float $threshold,
        bool $critical = false,
    ): void {
        if (! $this->enabled) {
            return;
        }

        $levelKey = $critical ? 'threshold_critical' : 'threshold_warning';
        $level = config("sentinel.levels.{$levelKey}", $critical ? 'critical' : 'warning');

        $context = $this->contextBuilder->build(
            eventType: "threshold_{$type}",
            additionalContext: [
                'threshold_type' => $type,
                'current_value' => $currentValue,
                'threshold' => $threshold,
                'is_critical' => $critical,
            ],
        );

        $this->log($level, $message, $context);

        if (config('sentinel.sentry.capture_threshold_alerts', true)) {
            $this->captureMessageToSentry($message, $level);
        }

        $this->recordMetric('threshold_alert', [
            'type' => $type,
            'critical' => $critical ? 'true' : 'false',
        ]);
    }

    /**
     * Log a custom event with specified level.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        // Limit context depth
        $maxDepth = config('sentinel.formatting.max_context_depth', 3);
        $context = $this->contextBuilder->limitDepth($context, $maxDepth);

        // Log to configured channels
        $this->logToChannels($level, $message, $context);

        // Store in database if enabled
        $this->storeInDatabase($level, $message, $context);

        // Dispatch event
        event(new MonitoringEventLogged($level, $message, $context));
    }

    /**
     * Log to configured notification channels.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logToChannels(string $level, string $message, array $context): void
    {
        $hasSlack = config('sentinel.slack.enabled', true) && config('sentinel.slack.webhook_url');
        $hasDiscord = config('sentinel.discord.enabled', false) && config('sentinel.discord.webhook_url');

        if (! $hasSlack && ! $hasDiscord) {
            return;
        }

        // Sanitize context before sending to external webhooks
        $sanitizedContext = $this->sanitizer->sanitize($context);

        // Dispatch async via queue if enabled
        if (config('sentinel.async.enabled', false)) {
            $job = new SendWebhookNotification($level, $message, $sanitizedContext);

            $queue = config('sentinel.async.queue');
            if ($queue) {
                $job->onQueue($queue);
            }

            $connection = config('sentinel.async.connection');
            if ($connection) {
                $job->onConnection($connection);
            }

            dispatch($job);

            return;
        }

        // Synchronous sending
        $this->sendToChannels($level, $message, $sanitizedContext);
    }

    /**
     * Send notifications synchronously to configured channels.
     *
     * @param  array<string, mixed>  $context
     */
    protected function sendToChannels(string $level, string $message, array $context): void
    {
        // Log to Slack
        if (config('sentinel.slack.enabled', true) && config('sentinel.slack.webhook_url')) {
            try {
                Log::channel('sentinel-slack')->{$level}($message, $context);
            } catch (Throwable $e) {
                Log::warning('[Sentinel] Slack notification failed: ' . $e->getMessage());
            }
        }

        // Log to Discord
        if (config('sentinel.discord.enabled', false) && config('sentinel.discord.webhook_url')) {
            try {
                Log::channel('sentinel-discord')->{$level}($message, $context);
            } catch (Throwable $e) {
                Log::warning('[Sentinel] Discord notification failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Store event in database if enabled.
     *
     * @param  array<string, mixed>  $context
     */
    protected function storeInDatabase(string $level, string $message, array $context): void
    {
        if (! $this->databaseEnabled) {
            return;
        }

        try {
            SentinelEvent::create([
                'level' => $level,
                'message' => $message,
                'event_type' => $context['event_type'] ?? null,
                'context' => $context,
                'user_id' => $context['user']['id'] ?? null,
                'ip_address' => $context['request']['ip'] ?? null,
                'url' => $context['request']['url'] ?? null,
                'environment' => $context['environment']['app_env'] ?? null,
            ]);
        } catch (Throwable $e) {
            if (! app()->isProduction()) {
                throw new \RuntimeException(
                    'Sentinel database storage failed. Run: php artisan migrate. ' . $e->getMessage(),
                    0,
                    $e,
                );
            }

            Log::warning('[Sentinel] Database storage failed: ' . $e->getMessage());
        }
    }

    /**
     * Capture exception to Sentry if available.
     */
    protected function captureToSentry(Throwable $exception): void
    {
        if (! config('sentinel.sentry.enabled', true)) {
            return;
        }

        if (! function_exists('app') || ! app()->bound('sentry')) {
            return;
        }

        try {
            app('sentry')->captureException($exception);
        } catch (Throwable) {
            // Sentry not available
        }
    }

    /**
     * Capture message to Sentry if available.
     */
    protected function captureMessageToSentry(string $message, string $level): void
    {
        if (! config('sentinel.sentry.enabled', true)) {
            return;
        }

        if (! function_exists('app') || ! app()->bound('sentry')) {
            return;
        }

        try {
            $severity = $this->mapLevelToSentrySeverity($level);
            app('sentry')->captureMessage($message, $severity);
        } catch (Throwable) {
            // Sentry not available
        }
    }

    /**
     * Map log level to Sentry severity.
     */
    protected function mapLevelToSentrySeverity(string $level): string
    {
        return match ($level) {
            'debug' => 'debug',
            'info', 'notice' => 'info',
            'warning' => 'warning',
            'error' => 'error',
            'critical', 'alert', 'emergency' => 'fatal',
            default => 'error',
        };
    }

    /**
     * Record a metric if collector is available.
     *
     * @param  array<string, string>  $labels
     */
    protected function recordMetric(string $name, array $labels = []): void
    {
        if ($this->metrics === null) {
            return;
        }

        $this->metrics->increment("sentinel_{$name}_total", $labels);
    }

    /**
     * Check if monitoring is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Temporarily disable monitoring.
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Re-enable monitoring.
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Execute a callback with monitoring disabled.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutMonitoring(callable $callback): mixed
    {
        $wasEnabled = $this->enabled;
        $this->enabled = false;

        try {
            return $callback();
        } finally {
            $this->enabled = $wasEnabled;
        }
    }
}
