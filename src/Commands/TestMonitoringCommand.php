<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Commands;

use Illuminate\Console\Command;
use Outlined\Sentinel\Services\MonitoringService;

class TestMonitoringCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sentinel:test
        {--level=info : The log level to test (debug, info, warning, error, critical)}
        {--message= : Custom message to send}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test notification to verify Sentinel is configured correctly';

    public function handle(MonitoringService $monitoring): int
    {
        $level = $this->option('level');
        $message = $this->option('message') ?? 'This is a test notification from Sentinel';

        $this->info('Sending test notification...');
        $this->newLine();

        // Show current configuration
        $this->line('Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Enabled', config('sentinel.enabled') ? 'Yes' : 'No'],
                ['Slack Enabled', config('sentinel.slack.enabled') ? 'Yes' : 'No'],
                ['Slack Webhook', config('sentinel.slack.webhook_url') ? 'Configured' : 'Not set'],
                ['Discord Enabled', config('sentinel.discord.enabled') ? 'Yes' : 'No'],
                ['Discord Webhook', config('sentinel.discord.webhook_url') ? 'Configured' : 'Not set'],
                ['Sentry Enabled', config('sentinel.sentry.enabled') ? 'Yes' : 'No'],
                ['Database Enabled', config('sentinel.database.enabled') ? 'Yes' : 'No'],
            ]
        );

        $this->newLine();

        if (! config('sentinel.enabled')) {
            $this->error('Sentinel is disabled. Enable it by setting SENTINEL_ENABLED=true');

            return self::FAILURE;
        }

        if (! config('sentinel.slack.webhook_url') && ! config('sentinel.discord.webhook_url')) {
            $this->error('No webhook URL configured. Set SENTINEL_SLACK_WEBHOOK or SENTINEL_DISCORD_WEBHOOK');

            return self::FAILURE;
        }

        // Send test notification
        $context = [
            'test' => true,
            'sent_from' => 'sentinel:test command',
            'environment' => config('app.env'),
        ];

        $monitoring->log($level, $message, [
            'event_type' => 'test',
            'data' => $context,
        ]);

        $this->info("Test notification sent successfully with level: {$level}");
        $this->line("Message: {$message}");
        $this->newLine();
        $this->line('Check your configured channels (Slack/Discord) for the notification.');

        return self::SUCCESS;
    }
}
