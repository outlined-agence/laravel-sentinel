<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Contracts;

use Outlined\Sentinel\Resources\ResourceStatus;

interface MonitorableResource
{
    /**
     * Get a unique identifier for this resource.
     */
    public function getIdentifier(): string;

    /**
     * Get a human-readable name for this resource.
     */
    public function getName(): string;

    /**
     * Check the resource and return its current status.
     */
    public function check(): ResourceStatus;

    /**
     * Get the threshold value that triggers a warning.
     */
    public function getWarningThreshold(): float;

    /**
     * Get the threshold value that triggers a critical alert.
     */
    public function getCriticalThreshold(): float;

    /**
     * Determine if higher values are better (e.g., balance) or worse (e.g., error rate).
     */
    public function higherIsBetter(): bool;
}
