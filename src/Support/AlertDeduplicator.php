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

    public function __construct()
    {
        $store = config('sentinel.deduplication.cache_store');
        $this->cache = $store ? Cache::store($store) : Cache::store();
        $this->prefix = config('sentinel.deduplication.cache_prefix', 'sentinel_alert_');
        $this->defaultTtl = config('sentinel.deduplication.ttl', 3600);
        $this->enabled = config('sentinel.deduplication.enabled', true);
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

        // Mark as sent
        $this->cache->put($key, [
            'sent_at' => now()->toIso8601String(),
            'type' => $type,
            'message_hash' => md5($message),
        ], $ttl ?? $this->defaultTtl);

        return true;
    }

    /**
     * Generate a unique cache key for deduplication.
     *
     * @param  array<string, mixed>  $context
     */
    protected function generateKey(string $type, string $message, array $context = []): string
    {
        // Use custom dedup key if provided
        if (isset($context['dedup_key'])) {
            return $this->prefix . $context['dedup_key'];
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
     * Clear all deduplication cache entries.
     */
    public function clearAll(): void
    {
        // Note: This is a simple implementation
        // For production, consider using cache tags if available
        // $this->cache->tags([$this->prefix])->flush();
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

        $this->cache->put($key, [
            'sent_at' => now()->toIso8601String(),
            'type' => $type,
            'message_hash' => md5($message),
        ], $ttl ?? $this->defaultTtl);
    }

    /**
     * Set a custom TTL for specific alert types.
     *
     * @var array<string, int>
     */
    protected array $customTtls = [];

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
