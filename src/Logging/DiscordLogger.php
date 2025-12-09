<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Logging;

use Monolog\Level;
use Monolog\Logger;

class DiscordLogger
{
    /**
     * Create a custom Monolog instance for the Discord channel.
     *
     * @param  array<string, mixed>  $config
     */
    public function __invoke(array $config): Logger
    {
        $webhookUrl = $config['webhook_url'] ?? config('sentinel.discord.webhook_url');

        if (empty($webhookUrl)) {
            // Return a null logger if webhook URL is not configured
            return new Logger('sentinel-discord');
        }

        $handler = new DiscordHandler(
            webhookUrl: $webhookUrl,
            username: $config['username'] ?? config('sentinel.discord.username', 'Sentinel'),
            avatarUrl: $config['avatar_url'] ?? config('sentinel.discord.avatar_url'),
            mentionRoleId: $config['mention_role_id'] ?? config('sentinel.discord.mention_role_id'),
            mentionOnLevels: $config['mention_on_levels'] ?? config('sentinel.discord.mention_on_levels', ['critical', 'alert', 'emergency']),
            timeout: $config['timeout'] ?? config('sentinel.discord.timeout', 5),
            level: Level::fromName($config['level'] ?? 'warning'),
        );

        $logger = new Logger('sentinel-discord');
        $logger->pushHandler($handler);

        return $logger;
    }
}
