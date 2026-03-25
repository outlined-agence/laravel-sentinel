<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWebhookNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 5;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $level,
        public readonly string $message,
        public readonly array $context,
    ) {}

    public function handle(): void
    {
        // Log to Slack
        if (config('sentinel.slack.enabled', true) && config('sentinel.slack.webhook_url')) {
            try {
                Log::channel('sentinel-slack')->{$this->level}($this->message, $this->context);
            } catch (Throwable) {
                // Fail silently
            }
        }

        // Log to Discord
        if (config('sentinel.discord.enabled', false) && config('sentinel.discord.webhook_url')) {
            try {
                Log::channel('sentinel-discord')->{$this->level}($this->message, $this->context);
            } catch (Throwable) {
                // Fail silently
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        // Silently fail - monitoring should not crash the app
    }
}
