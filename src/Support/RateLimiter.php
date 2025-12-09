<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class RateLimiter
{
    protected CacheRepository $cache;

    protected bool $enabled;

    protected int $maxPerMinute;

    protected int $maxPerHour;

    protected string $prefix = 'sentinel_rate_';

    public function __construct()
    {
        $store = config('sentinel.rate_limit.cache_store');
        $this->cache = $store ? Cache::store($store) : Cache::store();
        $this->enabled = config('sentinel.rate_limit.enabled', true);
        $this->maxPerMinute = config('sentinel.rate_limit.max_per_minute', 30);
        $this->maxPerHour = config('sentinel.rate_limit.max_per_hour', 200);
    }

    /**
     * Check if a notification is allowed under rate limits.
     */
    public function allow(string $channel): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $minuteKey = $this->getMinuteKey($channel);
        $hourKey = $this->getHourKey($channel);

        $minuteCount = (int) $this->cache->get($minuteKey, 0);
        $hourCount = (int) $this->cache->get($hourKey, 0);

        if ($minuteCount >= $this->maxPerMinute) {
            return false;
        }

        if ($hourCount >= $this->maxPerHour) {
            return false;
        }

        return true;
    }

    /**
     * Record a notification being sent.
     */
    public function hit(string $channel): void
    {
        if (! $this->enabled) {
            return;
        }

        $minuteKey = $this->getMinuteKey($channel);
        $hourKey = $this->getHourKey($channel);

        // Increment minute counter
        if ($this->cache->has($minuteKey)) {
            $this->cache->increment($minuteKey);
        } else {
            $this->cache->put($minuteKey, 1, 60);
        }

        // Increment hour counter
        if ($this->cache->has($hourKey)) {
            $this->cache->increment($hourKey);
        } else {
            $this->cache->put($hourKey, 1, 3600);
        }
    }

    /**
     * Get current rate limit status for a channel.
     *
     * @return array<string, mixed>
     */
    public function status(string $channel): array
    {
        $minuteKey = $this->getMinuteKey($channel);
        $hourKey = $this->getHourKey($channel);

        $minuteCount = (int) $this->cache->get($minuteKey, 0);
        $hourCount = (int) $this->cache->get($hourKey, 0);

        return [
            'channel' => $channel,
            'minute' => [
                'current' => $minuteCount,
                'max' => $this->maxPerMinute,
                'remaining' => max(0, $this->maxPerMinute - $minuteCount),
            ],
            'hour' => [
                'current' => $hourCount,
                'max' => $this->maxPerHour,
                'remaining' => max(0, $this->maxPerHour - $hourCount),
            ],
            'allowed' => $this->allow($channel),
        ];
    }

    /**
     * Reset rate limits for a channel.
     */
    public function reset(string $channel): void
    {
        $this->cache->forget($this->getMinuteKey($channel));
        $this->cache->forget($this->getHourKey($channel));
    }

    /**
     * Get remaining notifications allowed in current minute.
     */
    public function remainingPerMinute(string $channel): int
    {
        $minuteKey = $this->getMinuteKey($channel);
        $count = (int) $this->cache->get($minuteKey, 0);

        return max(0, $this->maxPerMinute - $count);
    }

    /**
     * Get remaining notifications allowed in current hour.
     */
    public function remainingPerHour(string $channel): int
    {
        $hourKey = $this->getHourKey($channel);
        $count = (int) $this->cache->get($hourKey, 0);

        return max(0, $this->maxPerHour - $count);
    }

    protected function getMinuteKey(string $channel): string
    {
        $minute = now()->format('Y-m-d-H-i');

        return "{$this->prefix}{$channel}_minute_{$minute}";
    }

    protected function getHourKey(string $channel): string
    {
        $hour = now()->format('Y-m-d-H');

        return "{$this->prefix}{$channel}_hour_{$hour}";
    }
}
