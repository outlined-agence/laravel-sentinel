<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Contracts;

interface NotificationChannel
{
    /**
     * Get the channel identifier (e.g., 'slack', 'discord').
     */
    public function getIdentifier(): string;

    /**
     * Check if the channel is enabled and configured.
     */
    public function isEnabled(): bool;

    /**
     * Send a notification through this channel.
     *
     * @param  array<string, mixed>  $context
     */
    public function send(string $level, string $message, array $context = []): bool;
}
