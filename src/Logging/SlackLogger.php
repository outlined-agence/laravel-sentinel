<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Logging;

use Monolog\Level;
use Monolog\Logger;

class SlackLogger
{
    /**
     * Create a custom Monolog instance for the Slack channel.
     *
     * @param  array<string, mixed>  $config
     */
    public function __invoke(array $config): Logger
    {
        $webhookUrl = $config['webhook_url'] ?? config('sentinel.slack.webhook_url');

        if (empty($webhookUrl)) {
            // Return a null logger if webhook URL is not configured
            return new Logger('sentinel-slack');
        }

        $handler = new SlackHandler(
            webhookUrl: $webhookUrl,
            channel: $config['channel'] ?? config('sentinel.slack.channel', '#monitoring'),
            username: $config['username'] ?? config('sentinel.slack.username', 'Sentinel'),
            iconEmoji: $config['icon_emoji'] ?? config('sentinel.slack.icon_emoji', ':shield:'),
            mentionUserId: $config['mention_user_id'] ?? config('sentinel.slack.mention_user_id'),
            mentionOnLevels: $config['mention_on_levels'] ?? config('sentinel.slack.mention_on_levels', ['critical', 'alert', 'emergency']),
            timeout: $config['timeout'] ?? config('sentinel.slack.timeout', 5),
            level: Level::fromName($config['level'] ?? 'warning'),
        );

        $logger = new Logger('sentinel-slack');
        $logger->pushHandler($handler);

        return $logger;
    }
}
