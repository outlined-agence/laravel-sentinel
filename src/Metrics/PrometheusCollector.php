<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Metrics;

use Outlined\Sentinel\Contracts\MetricsCollector;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\APC;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;
use Throwable;

/**
 * Prometheus metrics collector implementation.
 *
 * Requires: promphp/prometheus_client_php
 */
class PrometheusCollector implements MetricsCollector
{
    protected ?CollectorRegistry $registry = null;

    protected string $namespace;

    protected string $prefix;

    protected bool $available = false;

    public function __construct()
    {
        $this->namespace = config('sentinel.metrics.prometheus.namespace', 'app');
        $this->prefix = config('sentinel.metrics.prefix', 'sentinel');

        $this->initializeRegistry();
    }

    protected function initializeRegistry(): void
    {
        if (! class_exists(CollectorRegistry::class)) {
            return;
        }

        try {
            $storage = $this->createStorage();
            $this->registry = new CollectorRegistry($storage);
            $this->available = true;
        } catch (Throwable) {
            $this->available = false;
        }
    }

    protected function createStorage(): InMemory|Redis|APC
    {
        $storageType = config('sentinel.metrics.prometheus.storage', 'in_memory');

        return match ($storageType) {
            'redis' => $this->createRedisStorage(),
            'apc' => new APC(),
            default => new InMemory(),
        };
    }

    protected function createRedisStorage(): Redis
    {
        $connection = config('sentinel.metrics.prometheus.redis_connection', 'default');
        $config = config("database.redis.{$connection}", []);

        return new Redis([
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 6379,
            'password' => $config['password'] ?? null,
            'database' => $config['database'] ?? 0,
        ]);
    }

    public function increment(string $name, array $labels = [], float $value = 1): void
    {
        if (! $this->available || $this->registry === null) {
            return;
        }

        try {
            $counter = $this->registry->getOrRegisterCounter(
                $this->namespace,
                $this->prefix . '_' . $name,
                "Counter for {$name}",
                array_keys($labels)
            );

            $counter->incBy($value, array_values($labels));
        } catch (Throwable) {
            // Fail silently
        }
    }

    public function gauge(string $name, float $value, array $labels = []): void
    {
        if (! $this->available || $this->registry === null) {
            return;
        }

        try {
            $gauge = $this->registry->getOrRegisterGauge(
                $this->namespace,
                $this->prefix . '_' . $name,
                "Gauge for {$name}",
                array_keys($labels)
            );

            $gauge->set($value, array_values($labels));
        } catch (Throwable) {
            // Fail silently
        }
    }

    public function histogram(string $name, float $value, array $labels = [], ?array $buckets = null): void
    {
        if (! $this->available || $this->registry === null) {
            return;
        }

        try {
            $buckets = $buckets ?? [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];

            $histogram = $this->registry->getOrRegisterHistogram(
                $this->namespace,
                $this->prefix . '_' . $name,
                "Histogram for {$name}",
                array_keys($labels),
                $buckets
            );

            $histogram->observe($value, array_values($labels));
        } catch (Throwable) {
            // Fail silently
        }
    }

    public function timing(string $name, float $milliseconds, array $labels = []): void
    {
        // Convert milliseconds to seconds for Prometheus
        $this->histogram($name . '_seconds', $milliseconds / 1000, $labels);
    }

    public function render(): string
    {
        if (! $this->available || $this->registry === null) {
            return '';
        }

        try {
            $renderer = new RenderTextFormat();

            return $renderer->render($this->registry->getMetricFamilySamples());
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Check if Prometheus is available.
     */
    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * Get the registry instance.
     */
    public function getRegistry(): ?CollectorRegistry
    {
        return $this->registry;
    }
}
