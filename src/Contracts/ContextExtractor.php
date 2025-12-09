<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Contracts;

use Throwable;

interface ContextExtractor
{
    /**
     * Determine if this extractor should be applied.
     *
     * @param  array<string, mixed>  $context
     */
    public function shouldExtract(string $eventType, array $context, ?Throwable $exception = null): bool;

    /**
     * Extract additional context data.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function extract(string $eventType, array $context, ?Throwable $exception = null): array;

    /**
     * Get the priority of this extractor (higher = runs first).
     */
    public function priority(): int;
}
