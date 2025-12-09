<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Metrics;

use Outlined\Sentinel\Contracts\MetricsCollector;

/**
 * Null implementation that does nothing (used when metrics are disabled).
 */
class NullMetricsCollector implements MetricsCollector
{
    public function increment(string $name, array $labels = [], float $value = 1): void
    {
        // No-op
    }

    public function gauge(string $name, float $value, array $labels = []): void
    {
        // No-op
    }

    public function histogram(string $name, float $value, array $labels = [], ?array $buckets = null): void
    {
        // No-op
    }

    public function timing(string $name, float $milliseconds, array $labels = []): void
    {
        // No-op
    }

    public function render(): string
    {
        return '';
    }
}
