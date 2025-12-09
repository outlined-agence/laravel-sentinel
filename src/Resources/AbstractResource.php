<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Resources;

use Outlined\Sentinel\Contracts\MonitorableResource;

abstract class AbstractResource implements MonitorableResource
{
    protected float $warningThreshold = 0;

    protected float $criticalThreshold = 0;

    protected bool $higherIsBetter = true;

    public function getWarningThreshold(): float
    {
        return $this->warningThreshold;
    }

    public function getCriticalThreshold(): float
    {
        return $this->criticalThreshold;
    }

    public function higherIsBetter(): bool
    {
        return $this->higherIsBetter;
    }

    /**
     * Create a healthy status with current thresholds.
     */
    protected function healthy(float $value, string $message = ''): ResourceStatus
    {
        return ResourceStatus::healthy(
            value: $value,
            warningThreshold: $this->warningThreshold,
            criticalThreshold: $this->criticalThreshold,
            higherIsBetter: $this->higherIsBetter,
            message: $message,
        );
    }

    /**
     * Create a failed status.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function failed(string $message, array $metadata = []): ResourceStatus
    {
        return ResourceStatus::failed($message, $metadata);
    }
}
