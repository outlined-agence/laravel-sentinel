<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Metrics;

use Outlined\Sentinel\Contracts\MetricsCollector;
use Throwable;

/**
 * StatsD metrics collector implementation.
 *
 * Sends metrics to a StatsD server via UDP or TCP.
 */
class StatsdCollector implements MetricsCollector
{
    protected string $host;

    protected int $port;

    protected string $protocol;

    protected string $prefix;

    /** @var \Socket|resource|null */
    protected $udpSocket = null;

    /** @var resource|null */
    protected $tcpSocket = null;

    public function __construct()
    {
        $this->host = config('sentinel.metrics.statsd.host', '127.0.0.1');
        $this->port = (int) config('sentinel.metrics.statsd.port', 8125);
        $this->protocol = config('sentinel.metrics.statsd.protocol', 'udp');
        $this->prefix = config('sentinel.metrics.prefix', 'sentinel');
    }

    public function increment(string $name, array $labels = [], float $value = 1): void
    {
        $metricName = $this->buildMetricName($name);
        $tags = $this->buildTags($labels);
        $this->send("{$metricName}:{$value}|c{$tags}");
    }

    public function gauge(string $name, float $value, array $labels = []): void
    {
        $metricName = $this->buildMetricName($name);
        $tags = $this->buildTags($labels);
        $this->send("{$metricName}:{$value}|g{$tags}");
    }

    public function histogram(string $name, float $value, array $labels = [], ?array $buckets = null): void
    {
        // StatsD doesn't have native histograms, use timing instead
        $metricName = $this->buildMetricName($name);
        $tags = $this->buildTags($labels);
        $this->send("{$metricName}:{$value}|h{$tags}");
    }

    public function timing(string $name, float $milliseconds, array $labels = []): void
    {
        $metricName = $this->buildMetricName($name);
        $tags = $this->buildTags($labels);
        $this->send("{$metricName}:{$milliseconds}|ms{$tags}");
    }

    public function render(): string
    {
        // StatsD is push-based, no rendering needed
        return '';
    }

    /**
     * Build a metric name (without tags).
     */
    protected function buildMetricName(string $name): string
    {
        return $this->prefix . '.' . $name;
    }

    /**
     * Build DogStatsD tags string from labels.
     *
     * @param  array<string, string>  $labels
     */
    protected function buildTags(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }

        $tags = [];
        foreach ($labels as $key => $value) {
            $tags[] = "{$key}:{$value}";
        }

        return '|#' . implode(',', $tags);
    }

    /**
     * Send a metric to StatsD.
     */
    protected function send(string $message): void
    {
        try {
            if ($this->protocol === 'tcp') {
                $this->sendTcp($message);
            } else {
                $this->sendUdp($message);
            }
        } catch (Throwable) {
            // Fail silently
        }
    }

    /**
     * Send via UDP (reuses socket across calls).
     */
    protected function sendUdp(string $message): void
    {
        if ($this->udpSocket === null) {
            $this->udpSocket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

            if ($this->udpSocket === false) {
                $this->udpSocket = null;

                return;
            }
        }

        socket_sendto($this->udpSocket, $message, strlen($message), 0, $this->host, $this->port);
    }

    /**
     * Send via TCP (reuses socket across calls).
     */
    protected function sendTcp(string $message): void
    {
        if ($this->tcpSocket === null) {
            $this->tcpSocket = @fsockopen($this->host, $this->port, $errno, $errstr, 1);
        }

        if ($this->tcpSocket !== false && $this->tcpSocket !== null) {
            fwrite($this->tcpSocket, $message . "\n");
        }
    }

    /**
     * Close open connections.
     */
    public function __destruct()
    {
        if ($this->udpSocket !== null) {
            socket_close($this->udpSocket);
        }

        if ($this->tcpSocket !== null && $this->tcpSocket !== false) {
            fclose($this->tcpSocket);
        }
    }
}
