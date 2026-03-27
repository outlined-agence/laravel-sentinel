<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Support;

class ContextSanitizer
{
    protected bool $enabled;

    /** @var array<string> */
    protected array $sensitiveFields;

    /** @var array<string> */
    protected array $sensitivePatterns;

    protected string $mask;

    public function __construct()
    {
        $this->enabled = (bool) config('sentinel.sanitization.enabled', true);
        $this->mask = config('sentinel.sanitization.mask', '********');
        $this->sensitiveFields = config('sentinel.sanitization.fields', [
            'password',
            'password_confirmation',
            'secret',
            'token',
            'api_key',
            'api_secret',
            'access_token',
            'refresh_token',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
            'authorization',
        ]);
        $this->sensitivePatterns = config('sentinel.sanitization.patterns', []);
    }

    /**
     * Sanitize a context array before sending to external services.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function sanitize(array $context): array
    {
        if (! $this->enabled) {
            return $context;
        }

        return $this->sanitizeArray($context);
    }

    /**
     * Recursively sanitize an array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeArray(array $data, int $depth = 0): array
    {
        if ($depth > 10) {
            return $data;
        }

        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $result[$key] = $this->mask;

                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->sanitizeArray($value, $depth + 1);
            } elseif (is_string($value)) {
                $result[$key] = $this->sanitizeString($key, $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if a key name is sensitive.
     */
    protected function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach ($this->sensitiveFields as $field) {
            if ($normalized === strtolower($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize a string value (mask URL tokens, etc).
     */
    protected function sanitizeString(string $key, string $value): string
    {
        // Mask tokens in URLs
        if ($key === 'url' || $key === 'referrer') {
            return $this->sanitizeUrl($value);
        }

        // Apply custom patterns
        foreach ($this->sensitivePatterns as $pattern) {
            $value = preg_replace($pattern, $this->mask, $value) ?? $value;
        }

        return $value;
    }

    /**
     * Mask sensitive query parameters in URLs.
     */
    protected function sanitizeUrl(string $url): string
    {
        $parsed = parse_url($url);

        if (! isset($parsed['query'])) {
            return $url;
        }

        parse_str($parsed['query'], $params);

        $sensitiveUrlParams = ['token', 'key', 'api_key', 'secret', 'password', 'access_token', 'signature', 'sig'];

        $masked = false;
        foreach ($params as $paramKey => $paramValue) {
            if (in_array(strtolower($paramKey), $sensitiveUrlParams, true)) {
                $params[$paramKey] = $this->mask;
                $masked = true;
            }
        }

        if (! $masked) {
            return $url;
        }

        $newQuery = http_build_query($params);
        $base = strtok($url, '?');

        return $base . '?' . $newQuery;
    }
}
