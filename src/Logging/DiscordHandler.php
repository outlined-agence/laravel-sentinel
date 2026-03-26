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

class DiscordHandler extends AbstractProcessingHandler
{
    protected Client $client;

    protected string $webhookUrl;

    protected string $username;

    protected ?string $avatarUrl;

    protected ?string $mentionRoleId;

    /** @var array<string> */
    protected array $mentionOnLevels;

    protected int $timeout;

    protected AlertDeduplicator $deduplicator;

    protected RateLimiter $rateLimiter;

    /** @var array<string, int> Discord embed colors as decimal */
    protected array $colors;

    /** @var array<string, string> */
    protected array $emojis;

    public function __construct(
        string $webhookUrl,
        string $username = 'Sentinel',
        ?string $avatarUrl = null,
        ?string $mentionRoleId = null,
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
        $this->username = $username;
        $this->avatarUrl = $avatarUrl;
        $this->mentionRoleId = $mentionRoleId;
        $this->mentionOnLevels = $mentionOnLevels;
        $this->timeout = $timeout;

        $this->client = $client ?? new Client(['timeout' => $timeout]);
        $this->deduplicator = $deduplicator ?? app(AlertDeduplicator::class);
        $this->rateLimiter = $rateLimiter ?? app(RateLimiter::class);

        // Discord colors are decimal integers
        $this->colors = [
            'debug' => 0x808080,     // Gray
            'info' => 0x3498db,      // Blue
            'notice' => 0x2ecc71,    // Green
            'warning' => 0xf39c12,   // Orange
            'error' => 0xe74c3c,     // Red
            'critical' => 0x9b59b6,  // Purple
            'alert' => 0xe91e63,     // Pink
            'emergency' => 0x000000, // Black
        ];

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
        if (! $this->rateLimiter->attempt('discord')) {
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
        $color = $this->colors[$levelName] ?? 0x808080;

        $payload = [
            'username' => $this->username,
            'embeds' => $this->buildEmbeds($record, $color),
        ];

        if ($this->avatarUrl) {
            $payload['avatar_url'] = $this->avatarUrl;
        }

        // Add mention if needed
        if ($this->shouldMention($levelName)) {
            $payload['content'] = "<@&{$this->mentionRoleId}>";
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildEmbeds(LogRecord $record, int $color): array
    {
        $levelName = strtolower($record->level->name);
        $emoji = $this->emojis[$levelName] ?? '';
        $context = $record->context;

        $embeds = [];

        // Main embed
        $mainEmbed = [
            'title' => "{$emoji} [" . strtoupper($levelName) . ']',
            'description' => $record->message,
            'color' => $color,
            'timestamp' => $record->datetime->format('c'),
            'footer' => [
                'text' => config('app.name') . ' | ' . config('app.env'),
            ],
            'fields' => [],
        ];

        // Event type
        if (isset($context['event_type'])) {
            $mainEmbed['fields'][] = [
                'name' => 'Event Type',
                'value' => "`{$context['event_type']}`",
                'inline' => true,
            ];
        }

        // Environment info
        if (isset($context['environment'])) {
            $mainEmbed['fields'][] = [
                'name' => 'Environment',
                'value' => $context['environment']['app_env'] ?? 'unknown',
                'inline' => true,
            ];
        }

        // User info
        if (! empty($context['user'])) {
            $userInfo = $this->formatUserInfo($context['user']);
            if ($userInfo) {
                $mainEmbed['fields'][] = [
                    'name' => 'User',
                    'value' => $userInfo,
                    'inline' => true,
                ];
            }
        }

        // Request info
        if (! empty($context['request'])) {
            $mainEmbed['fields'][] = [
                'name' => 'URL',
                'value' => $context['request']['method'] . ' ' . ($context['request']['url'] ?? 'N/A'),
                'inline' => false,
            ];

            if (! empty($context['request']['ip'])) {
                $mainEmbed['fields'][] = [
                    'name' => 'IP',
                    'value' => $context['request']['ip'],
                    'inline' => true,
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
                $mainEmbed['fields'][] = [
                    'name' => ucfirst(str_replace('_', ' ', $key)),
                    'value' => strlen($formatted) > 1024 ? substr($formatted, 0, 1021) . '...' : $formatted,
                    'inline' => strlen($formatted) < 40,
                ];
            }
        }

        // Threshold specific fields
        if (isset($context['threshold'])) {
            $mainEmbed['fields'][] = [
                'name' => 'Current Value',
                'value' => (string) ($context['current_value'] ?? 'N/A'),
                'inline' => true,
            ];
            $mainEmbed['fields'][] = [
                'name' => 'Threshold',
                'value' => (string) $context['threshold'],
                'inline' => true,
            ];
        }

        $embeds[] = $mainEmbed;

        // Exception embed
        if (! empty($context['exception'])) {
            $embeds[] = $this->buildExceptionEmbed($context['exception'], $color);
        }

        return $embeds;
    }

    /**
     * @param  array<string, mixed>  $exception
     * @return array<string, mixed>
     */
    protected function buildExceptionEmbed(array $exception, int $color): array
    {
        $embed = [
            'title' => 'Exception: ' . ($exception['class'] ?? 'Unknown'),
            'description' => $exception['message'] ?? 'No message',
            'color' => $color,
            'fields' => [
                [
                    'name' => 'Location',
                    'value' => ($exception['file'] ?? '') . ':' . ($exception['line'] ?? ''),
                    'inline' => false,
                ],
            ],
        ];

        // Add stack trace
        if (config('sentinel.formatting.include_stack_trace', true) && ! empty($exception['trace'])) {
            $traceText = $this->formatStackTrace($exception['trace']);
            // Discord has a 1024 character limit for field values
            if (strlen($traceText) > 1000) {
                $traceText = substr($traceText, 0, 997) . '...';
            }
            $embed['fields'][] = [
                'name' => 'Stack Trace',
                'value' => "```\n{$traceText}\n```",
                'inline' => false,
            ];
        }

        return $embed;
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
            return '```json' . "\n" . json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```";
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
            $lines[] = "#{$i} {$file}:{$line}";
            $lines[] = "   {$call}";
        }

        return implode("\n", $lines);
    }

    protected function shouldMention(string $levelName): bool
    {
        if (empty($this->mentionRoleId)) {
            return false;
        }

        return in_array($levelName, $this->mentionOnLevels, true);
    }
}
