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

    /** @var resource|null */
    protected $socket = null;

    public function __construct()
    {
        $this->host = config('sentinel.metrics.statsd.host', '127.0.0.1');
        $this->port = config('sentinel.metrics.statsd.port', 8125);
        $this->protocol = config('sentinel.metrics.statsd.protocol', 'udp');
        $this->prefix = config('sentinel.metrics.prefix', 'sentinel');
    }

    public function increment(string $name, array $labels = [], float $value = 1): void
    {
        $metricName = $this->buildMetricName($name, $labels);
        $this->send("{$metricName}:{$value}|c");
    }

    public function gauge(string $name, float $value, array $labels = []): void
    {
        $metricName = $this->buildMetricName($name, $labels);
        $this->send("{$metricName}:{$value}|g");
    }

    public function histogram(string $name, float $value, array $labels = [], ?array $buckets = null): void
    {
        // StatsD doesn't have native histograms, use timing instead
        $metricName = $this->buildMetricName($name, $labels);
        $this->send("{$metricName}:{$value}|h");
    }

    public function timing(string $name, float $milliseconds, array $labels = []): void
    {
        $metricName = $this->buildMetricName($name, $labels);
        $this->send("{$metricName}:{$milliseconds}|ms");
    }

    public function render(): string
    {
        // StatsD is push-based, no rendering needed
        return '';
    }

    /**
     * Build a metric name with labels.
     *
     * @param  array<string, string>  $labels
     */
    protected function buildMetricName(string $name, array $labels): string
    {
        $fullName = $this->prefix . '.' . $name;

        // Append labels as tags (StatsD DogStatsD format)
        if (! empty($labels)) {
            $tags = [];
            foreach ($labels as $key => $value) {
                $tags[] = "{$key}:{$value}";
            }
            $fullName .= '|#' . implode(',', $tags);
        }

        return $fullName;
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
     * Send via UDP.
     */
    protected function sendUdp(string $message): void
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($socket === false) {
            return;
        }

        socket_sendto($socket, $message, strlen($message), 0, $this->host, $this->port);
        socket_close($socket);
    }

    /**
     * Send via TCP.
     */
    protected function sendTcp(string $message): void
    {
        if ($this->socket === null) {
            $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 1);
        }

        if ($this->socket !== false && $this->socket !== null) {
            fwrite($this->socket, $message . "\n");
        }
    }

    /**
     * Close the TCP connection.
     */
    public function __destruct()
    {
        if ($this->socket !== null && $this->socket !== false) {
            fclose($this->socket);
        }
    }
}
