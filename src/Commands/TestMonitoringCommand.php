<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Outlined\Sentinel\Services\MonitoringService;
use Throwable;

class TestMonitoringCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sentinel:test
        {--level=warning : The log level to test (debug, info, warning, error, critical)}
        {--message= : Custom message to send}
        {--webhook-only : Only test webhook connectivity, do not send through the logging pipeline}';

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

        $this->info('Sentinel Diagnostic');
        $this->newLine();

        // Show current configuration
        $this->line('Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Enabled', config('sentinel.enabled') ? 'Yes' : 'No'],
                ['Slack Enabled', config('sentinel.slack.enabled') ? 'Yes' : 'No'],
                ['Slack Webhook', config('sentinel.slack.webhook_url') ? 'Configured' : 'Not set'],
                ['Slack Level', config('sentinel.slack.level', 'debug')],
                ['Discord Enabled', config('sentinel.discord.enabled') ? 'Yes' : 'No'],
                ['Discord Webhook', config('sentinel.discord.webhook_url') ? 'Configured' : 'Not set'],
                ['Discord Level', config('sentinel.discord.level', 'debug')],
                ['Sentry Enabled', config('sentinel.sentry.enabled') ? 'Yes' : 'No'],
                ['Database Enabled', config('sentinel.database.enabled') ? 'Yes' : 'No'],
                ['Async Enabled', config('sentinel.async.enabled') ? 'Yes' : 'No'],
            ]
        );

        $this->newLine();

        if (! config('sentinel.enabled')) {
            $this->error('Sentinel is disabled. Enable it by setting SENTINEL_ENABLED=true');

            return self::FAILURE;
        }

        $hasSlack = config('sentinel.slack.enabled') && config('sentinel.slack.webhook_url');
        $hasDiscord = config('sentinel.discord.enabled') && config('sentinel.discord.webhook_url');

        if (! $hasSlack && ! $hasDiscord) {
            $this->error('No webhook URL configured. Set SENTINEL_SLACK_WEBHOOK or SENTINEL_DISCORD_WEBHOOK');

            return self::FAILURE;
        }

        // Step 1: Test webhook connectivity directly
        $this->line('Testing webhook connectivity...');
        $webhookOk = true;

        if ($hasSlack) {
            $webhookOk = $this->testSlackWebhook() && $webhookOk;
        }

        if ($hasDiscord) {
            $webhookOk = $this->testDiscordWebhook() && $webhookOk;
        }

        if (! $webhookOk) {
            $this->error('Webhook connectivity test failed. Check your webhook URLs.');

            return self::FAILURE;
        }

        if ($this->option('webhook-only')) {
            $this->newLine();
            $this->info('Webhook connectivity OK. Use without --webhook-only to send a full test notification.');

            return self::SUCCESS;
        }

        // Step 2: Send through the full pipeline
        $this->newLine();
        $this->line("Sending test notification through pipeline (level: {$level})...");

        $monitoring->log($level, $message, [
            'event_type' => 'test',
            'data' => [
                'test' => true,
                'sent_from' => 'sentinel:test command',
                'environment' => config('app.env'),
            ],
        ]);

        $this->newLine();
        $this->info("Test notification sent with level: {$level}");
        $this->line("Message: {$message}");
        $this->newLine();
        $this->line('Check your configured channels for the notification.');
        $this->line('<comment>Tip:</comment> If nothing appears, check that the channel log level allows "' . $level . '".');
        $this->line('     Current Slack level: ' . config('sentinel.slack.level', 'debug'));
        $this->line('     Current Discord level: ' . config('sentinel.discord.level', 'debug'));

        return self::SUCCESS;
    }

    /**
     * Test Slack webhook with a direct HTTP call.
     */
    protected function testSlackWebhook(): bool
    {
        $url = config('sentinel.slack.webhook_url');

        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->post($url, [
                'json' => [
                    'text' => ':white_check_mark: Sentinel connectivity test - webhook is working!',
                ],
            ]);

            $status = $response->getStatusCode();

            if ($status >= 200 && $status < 300) {
                $this->line('  <info>✓</info> Slack webhook: OK (HTTP ' . $status . ')');

                return true;
            }

            $this->line('  <error>✗</error> Slack webhook: HTTP ' . $status);

            return false;
        } catch (Throwable $e) {
            $this->line('  <error>✗</error> Slack webhook: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Test Discord webhook with a direct HTTP call.
     */
    protected function testDiscordWebhook(): bool
    {
        $url = config('sentinel.discord.webhook_url');

        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->post($url, [
                'json' => [
                    'content' => '✅ Sentinel connectivity test - webhook is working!',
                ],
            ]);

            $status = $response->getStatusCode();

            if ($status >= 200 && $status < 300) {
                $this->line('  <info>✓</info> Discord webhook: OK (HTTP ' . $status . ')');

                return true;
            }

            $this->line('  <error>✗</error> Discord webhook: HTTP ' . $status);

            return false;
        } catch (Throwable $e) {
            $this->line('  <error>✗</error> Discord webhook: ' . $e->getMessage());

            return false;
        }
    }
}
