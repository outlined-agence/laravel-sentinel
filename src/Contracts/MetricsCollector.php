<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Contracts;

interface MetricsCollector
{
    /**
     * Increment a counter metric.
     *
     * @param  array<string, string>  $labels
     */
    public function increment(string $name, array $labels = [], float $value = 1): void;

    /**
     * Set a gauge metric value.
     *
     * @param  array<string, string>  $labels
     */
    public function gauge(string $name, float $value, array $labels = []): void;

    /**
     * Observe a value for a histogram metric.
     *
     * @param  array<string, string>  $labels
     * @param  array<float>|null  $buckets
     */
    public function histogram(string $name, float $value, array $labels = [], ?array $buckets = null): void;

    /**
     * Record a timing metric (in milliseconds).
     *
     * @param  array<string, string>  $labels
     */
    public function timing(string $name, float $milliseconds, array $labels = []): void;

    /**
     * Get the metrics output for exposition (e.g., Prometheus format).
     */
    public function render(): string;
}
