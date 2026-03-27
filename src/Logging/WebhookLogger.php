<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Logging;

use Monolog\Level;
use Monolog\Logger;

class WebhookLogger
{
    /**
     * Create a custom Monolog instance for the webhook channel.
     *
     * @param  array<string, mixed>  $config
     */
    public function __invoke(array $config): Logger
    {
        $endpointUrl = $config['endpoint_url'] ?? config('sentinel.webhook.endpoint_url');
        $secret = $config['secret'] ?? config('sentinel.webhook.secret');

        if (empty($endpointUrl) || empty($secret)) {
            // Return a null logger if not configured
            return new Logger('sentinel-webhook');
        }

        $handler = new WebhookHandler(
            endpointUrl: $endpointUrl,
            secret: $secret,
            timeout: (int) ($config['timeout'] ?? config('sentinel.webhook.timeout', 5)),
            level: Level::fromName($config['level'] ?? config('sentinel.webhook.level', 'debug')),
        );

        $logger = new Logger('sentinel-webhook');
        $logger->pushHandler($handler);

        return $logger;
    }
}
