<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Logging;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Outlined\Sentinel\Support\AlertDeduplicator;
use Outlined\Sentinel\Support\RateLimiter;
use Throwable;

class WebhookHandler extends AbstractProcessingHandler
{
    protected Client $client;

    protected string $endpointUrl;

    protected string $secret;

    protected int $timeout;

    protected AlertDeduplicator $deduplicator;

    protected RateLimiter $rateLimiter;

    public function __construct(
        string $endpointUrl,
        string $secret,
        int $timeout = 5,
        Level $level = Level::Warning,
        bool $bubble = true,
        ?Client $client = null,
        ?AlertDeduplicator $deduplicator = null,
        ?RateLimiter $rateLimiter = null,
    ) {
        parent::__construct($level, $bubble);

        $this->endpointUrl = $endpointUrl;
        $this->secret = $secret;
        $this->timeout = $timeout;

        $this->client = $client ?? new Client(['timeout' => $timeout]);
        $this->deduplicator = $deduplicator ?? app(AlertDeduplicator::class);
        $this->rateLimiter = $rateLimiter ?? app(RateLimiter::class);
    }

    protected function write(LogRecord $record): void
    {
        $levelName = strtolower($record->level->name);
        $context = $record->context;

        // Check deduplication
        $dedupType = $context['event_type'] ?? $levelName;
        if (! $this->deduplicator->shouldSend($dedupType, $record->message, $context)) {
            return;
        }

        // Check rate limiting (atomic check + increment)
        if (! $this->rateLimiter->attempt('webhook')) {
            return;
        }

        try {
            $payload = $this->buildPayload($record);
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($jsonPayload === false) {
                return;
            }

            $signature = hash_hmac('sha256', $jsonPayload, $this->secret);

            $this->client->post($this->endpointUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Sentinel-Signature' => $signature,
                    'X-Sentinel-Timestamp' => (string) time(),
                ],
                'body' => $jsonPayload,
            ]);
        } catch (GuzzleException|Throwable) {
            // Fail silently to not block the application
        }
    }

    /**
     * Build the webhook payload following the contract spec.
     *
     * @return array<string, mixed>
     */
    protected function buildPayload(LogRecord $record): array
    {
        $context = $record->context;
        $levelName = strtolower($record->level->name);

        $payload = [
            'message' => $record->message,
            'level' => $levelName,
        ];

        // Exception data
        if (! empty($context['exception'])) {
            $exception = $context['exception'];
            $payload['exception_class'] = $exception['class'] ?? null;
            $payload['file'] = $exception['file'] ?? null;
            $payload['line'] = $exception['line'] ?? null;

            if (! empty($exception['trace'])) {
                $payload['stack_trace'] = $this->formatStackTrace($exception['trace']);
            }
        } else {
            // For non-exception events, use event_type as class
            $payload['exception_class'] = $context['event_type'] ?? null;
        }

        // Request data
        if (! empty($context['request'])) {
            $request = $context['request'];
            $payload['request_data'] = array_filter([
                'url' => $request['url'] ?? null,
                'method' => $request['method'] ?? null,
                'ip' => $request['ip'] ?? null,
            ]);
        }

        // Environment
        if (! empty($context['environment'])) {
            $payload['environment'] = $context['environment']['app_env'] ?? null;
            $payload['app_name'] = $context['environment']['app_name'] ?? null;
        }

        // User
        if (! empty($context['user'])) {
            $payload['user_id'] = $context['user']['id'] ?? null;
        }

        // Event type
        if (isset($context['event_type'])) {
            $payload['event_type'] = $context['event_type'];
        }

        // Timestamp
        $payload['timestamp'] = $record->datetime->format('c');

        return $payload;
    }

    /**
     * Format stack trace array into a string.
     *
     * @param  array<int, array<string, mixed>>  $trace
     */
    protected function formatStackTrace(array $trace): string
    {
        $lines = [];
        foreach ($trace as $i => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? '?';
            $class = $frame['class'] ?? '';
            $function = $frame['function'] ?? '';

            $call = $class ? "{$class}::{$function}()" : "{$function}()";
            $lines[] = "#{$i} {$file}({$line}): {$call}";
        }

        return implode("\n", $lines);
    }

    /**
     * Generate HMAC signature for a payload.
     * Exposed as static for verification on the receiving end.
     */
    public static function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify a webhook signature.
     * Use this on the receiving end to validate incoming webhooks.
     */
    public static function verify(string $payload, string $signature, string $secret): bool
    {
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
