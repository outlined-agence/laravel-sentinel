<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class AlertDeduplicator
{
    protected CacheRepository $cache;

    protected string $prefix;

    protected int $defaultTtl;

    protected bool $enabled;

    /** @var array<string, int> */
    protected array $customTtls = [];

    /** @var array<string> Keys tracked for clearAll support */
    protected array $trackedKeys = [];

    protected string $trackingKey = 'sentinel_dedup_keys';

    public function __construct()
    {
        $store = config('sentinel.deduplication.cache_store');
        $this->cache = $store ? Cache::store($store) : Cache::store();
        $this->prefix = config('sentinel.deduplication.cache_prefix', 'sentinel_alert_');
        $this->defaultTtl = (int) config('sentinel.deduplication.ttl', 3600);
        $this->enabled = (bool) config('sentinel.deduplication.enabled', true);
    }

    /**
     * Check if an alert should be sent (not a duplicate).
     *
     * @param  array<string, mixed>  $context
     */
    public function shouldSend(string $type, string $message, array $context = [], ?int $ttl = null): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $key = $this->generateKey($type, $message, $context);

        if ($this->cache->has($key)) {
            return false;
        }

        $effectiveTtl = $ttl ?? $this->customTtls[$type] ?? $this->defaultTtl;

        // Mark as sent
        $this->cache->put($key, [
            'sent_at' => now()->toIso8601String(),
            'type' => $type,
            'message_hash' => md5($message),
        ], $effectiveTtl);

        $this->trackKey($key);

        return true;
    }

    /**
     * Generate a unique cache key for deduplication.
     *
     * @param  array<string, mixed>  $context
     */
    protected function generateKey(string $type, string $message, array $context = []): string
    {
        // Use custom dedup key if provided (hashed to prevent cache key injection)
        if (isset($context['dedup_key'])) {
            return $this->prefix . md5((string) $context['dedup_key']);
        }

        // Generate key from type and message hash
        $hash = md5($type . '|' . $message);

        return $this->prefix . $hash;
    }

    /**
     * Force clear a specific alert from deduplication cache.
     */
    public function clear(string $type, string $message): void
    {
        $key = $this->generateKey($type, $message);
        $this->cache->forget($key);
    }

    /**
     * Clear all tracked deduplication cache entries.
     */
    public function clearAll(): void
    {
        $keys = $this->cache->get($this->trackingKey, []);

        foreach ($keys as $key) {
            $this->cache->forget($key);
        }

        $this->cache->forget($this->trackingKey);
    }

    /**
     * Track a cache key for clearAll support.
     */
    protected function trackKey(string $key): void
    {
        $keys = $this->cache->get($this->trackingKey, []);
        $keys[] = $key;
        // Keep only unique keys and limit the tracking list
        $keys = array_unique($keys);
        $this->cache->put($this->trackingKey, $keys, $this->defaultTtl * 2);
    }

    /**
     * Check if an alert is currently deduplicated (suppressed).
     *
     * @param  array<string, mixed>  $context
     */
    public function isDeduplicated(string $type, string $message, array $context = []): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $key = $this->generateKey($type, $message, $context);

        return $this->cache->has($key);
    }

    /**
     * Get deduplication info for an alert.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    public function getInfo(string $type, string $message, array $context = []): ?array
    {
        $key = $this->generateKey($type, $message, $context);

        return $this->cache->get($key);
    }

    /**
     * Manually mark an alert as sent (for external deduplication).
     *
     * @param  array<string, mixed>  $context
     */
    public function markAsSent(string $type, string $message, array $context = [], ?int $ttl = null): void
    {
        $key = $this->generateKey($type, $message, $context);

        $effectiveTtl = $ttl ?? $this->customTtls[$type] ?? $this->defaultTtl;

        $this->cache->put($key, [
            'sent_at' => now()->toIso8601String(),
            'type' => $type,
            'message_hash' => md5($message),
        ], $effectiveTtl);

        $this->trackKey($key);
    }

    /**
     * Register a custom TTL for a specific alert type.
     */
    public function setTtlForType(string $type, int $ttl): void
    {
        $this->customTtls[$type] = $ttl;
    }

    /**
     * Get the TTL for a specific alert type.
     */
    public function getTtlForType(string $type): int
    {
        return $this->customTtls[$type] ?? $this->defaultTtl;
    }
}
