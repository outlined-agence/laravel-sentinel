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

class SlackHandler extends AbstractProcessingHandler
{
    protected Client $client;

    protected string $webhookUrl;

    protected string $channel;

    protected string $username;

    protected string $iconEmoji;

    protected ?string $mentionUserId;

    /** @var array<string> */
    protected array $mentionOnLevels;

    protected int $timeout;

    protected AlertDeduplicator $deduplicator;

    protected RateLimiter $rateLimiter;

    /** @var array<string, string> */
    protected array $colors;

    /** @var array<string, string> */
    protected array $emojis;

    public function __construct(
        string $webhookUrl,
        string $channel = '#monitoring',
        string $username = 'Sentinel',
        string $iconEmoji = ':shield:',
        ?string $mentionUserId = null,
        array $mentionOnLevels = ['critical', 'alert', 'emergency'],
        int $timeout = 5,
        Level $level = Level::Warning,
        bool $bubble = true,
        ?Client $client = null,
        ?AlertDeduplicator $deduplicator = null,
        ?RateLimiter $rateLimiter = null,
    ) {
        parent::__construct($level, $bubble);

        $this->webhookUrl = $webhookUrl;
        $this->channel = $channel;
        $this->username = $username;
        $this->iconEmoji = $iconEmoji;
        $this->mentionUserId = $mentionUserId;
        $this->mentionOnLevels = $mentionOnLevels;
        $this->timeout = $timeout;

        $this->client = $client ?? new Client(['timeout' => $timeout]);
        $this->deduplicator = $deduplicator ?? app(AlertDeduplicator::class);
        $this->rateLimiter = $rateLimiter ?? app(RateLimiter::class);

        $this->colors = config('sentinel.formatting.colors', [
            'debug' => '#808080',
            'info' => '#3498db',
            'notice' => '#2ecc71',
            'warning' => '#f39c12',
            'error' => '#e74c3c',
            'critical' => '#9b59b6',
            'alert' => '#e91e63',
            'emergency' => '#000000',
        ]);

        $this->emojis = config('sentinel.formatting.emojis', [
            'debug' => ':mag:',
            'info' => ':information_source:',
            'notice' => ':white_check_mark:',
            'warning' => ':warning:',
            'error' => ':x:',
            'critical' => ':fire:',
            'alert' => ':rotating_light:',
            'emergency' => ':skull:',
        ]);
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
        if (! $this->rateLimiter->attempt('slack')) {
            return;
        }

        try {
            $payload = $this->buildPayload($record);
            $this->client->post($this->webhookUrl, [
                'json' => $payload,
            ]);
        } catch (GuzzleException|Throwable) {
            // Fail silently to not block the application
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(LogRecord $record): array
    {
        $levelName = strtolower($record->level->name);
        $emoji = $this->emojis[$levelName] ?? ':bell:';
        $color = $this->colors[$levelName] ?? '#808080';

        $text = $this->buildText($record, $levelName, $emoji);
        $attachments = $this->buildAttachments($record, $color);

        return [
            'channel' => $this->channel,
            'username' => $this->username,
            'icon_emoji' => $this->iconEmoji,
            'text' => $text,
            'attachments' => $attachments,
        ];
    }

    protected function buildText(LogRecord $record, string $levelName, string $emoji): string
    {
        $parts = [];

        // Add mention if needed
        if ($this->shouldMention($levelName)) {
            $parts[] = "<@{$this->mentionUserId}>";
        }

        // Add emoji and level
        $parts[] = "{$emoji} *[" . strtoupper($levelName) . "]*";

        // Add event type if present
        $eventType = $record->context['event_type'] ?? null;
        if ($eventType) {
            $parts[] = "`{$eventType}`";
        }

        // Add message
        $parts[] = $record->message;

        return implode(' ', $parts);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildAttachments(LogRecord $record, string $color): array
    {
        $attachments = [];
        $context = $record->context;

        // Main attachment with fields
        $mainAttachment = [
            'color' => $color,
            'fields' => [],
            'footer' => config('app.name') . ' | ' . config('app.env'),
            'ts' => $record->datetime->getTimestamp(),
        ];

        // Environment info
        if (isset($context['environment'])) {
            $mainAttachment['fields'][] = [
                'title' => 'Environment',
                'value' => $context['environment']['app_env'] ?? 'unknown',
                'short' => true,
            ];
        }

        // User info
        if (! empty($context['user'])) {
            $userInfo = $this->formatUserInfo($context['user']);
            if ($userInfo) {
                $mainAttachment['fields'][] = [
                    'title' => 'User',
                    'value' => $userInfo,
                    'short' => true,
                ];
            }
        }

        // Request info
        if (! empty($context['request'])) {
            $mainAttachment['fields'][] = [
                'title' => 'URL',
                'value' => $context['request']['method'] . ' ' . ($context['request']['url'] ?? 'N/A'),
                'short' => false,
            ];

            if (! empty($context['request']['ip'])) {
                $mainAttachment['fields'][] = [
                    'title' => 'IP',
                    'value' => $context['request']['ip'],
                    'short' => true,
                ];
            }
        }

        // Custom data fields
        if (! empty($context['data'])) {
            foreach ($context['data'] as $key => $value) {
                if ($key === 'dedup_key') {
                    continue;
                }
                $formatted = $this->formatValue($value);
                $mainAttachment['fields'][] = [
                    'title' => ucfirst(str_replace('_', ' ', $key)),
                    'value' => strlen($formatted) > 1024 ? substr($formatted, 0, 1021) . '...' : $formatted,
                    'short' => strlen($formatted) < 40,
                ];
            }
        }

        // Threshold specific fields
        if (isset($context['threshold'])) {
            $mainAttachment['fields'][] = [
                'title' => 'Current Value',
                'value' => (string) ($context['current_value'] ?? 'N/A'),
                'short' => true,
            ];
            $mainAttachment['fields'][] = [
                'title' => 'Threshold',
                'value' => (string) $context['threshold'],
                'short' => true,
            ];
        }

        if (! empty($mainAttachment['fields'])) {
            $attachments[] = $mainAttachment;
        }

        // Exception attachment
        if (! empty($context['exception'])) {
            $attachments[] = $this->buildExceptionAttachment($context['exception'], $color);
        }

        return $attachments;
    }

    /**
     * @param  array<string, mixed>  $exception
     * @return array<string, mixed>
     */
    protected function buildExceptionAttachment(array $exception, string $color): array
    {
        $attachment = [
            'color' => $color,
            'title' => $exception['class'] ?? 'Exception',
            'text' => $exception['message'] ?? 'No message',
            'fields' => [
                [
                    'title' => 'File',
                    'value' => ($exception['file'] ?? '') . ':' . ($exception['line'] ?? ''),
                    'short' => false,
                ],
            ],
        ];

        // Add stack trace as code block
        if (config('sentinel.formatting.include_stack_trace', true) && ! empty($exception['trace'])) {
            $traceText = $this->formatStackTrace($exception['trace']);
            // Slack also has field value limits
            if (strlen($traceText) > 1000) {
                $traceText = substr($traceText, 0, 997) . '...';
            }
            $attachment['fields'][] = [
                'title' => 'Stack Trace',
                'value' => "```\n{$traceText}\n```",
                'short' => false,
            ];
        }

        return $attachment;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function formatUserInfo(array $user): string
    {
        $parts = [];

        if (isset($user['id'])) {
            $parts[] = "ID: {$user['id']}";
        }

        if (isset($user['email'])) {
            $parts[] = $user['email'];
        }

        if (isset($user['name'])) {
            $parts[] = "({$user['name']})";
        }

        return implode(' ', $parts);
    }

    protected function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[array]';
        }

        if (is_object($value)) {
            return '[object: ' . get_class($value) . ']';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
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
            $lines[] = "#{$i} {$file}:{$line} {$call}";
        }

        return implode("\n", $lines);
    }

    protected function shouldMention(string $levelName): bool
    {
        if (empty($this->mentionUserId)) {
            return false;
        }

        return in_array($levelName, $this->mentionOnLevels, true);
    }
}
