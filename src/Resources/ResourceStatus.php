<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Resources;

use JsonSerializable;

final class ResourceStatus implements JsonSerializable
{
    public function __construct(
        public readonly bool $success,
        public readonly float $value,
        public readonly float $warningThreshold,
        public readonly float $criticalThreshold,
        public readonly bool $higherIsBetter,
        public readonly string $message = '',
        public readonly array $metadata = [],
    ) {}

    public function isWarning(): bool
    {
        if ($this->higherIsBetter) {
            return $this->value <= $this->warningThreshold && $this->value > $this->criticalThreshold;
        }

        return $this->value >= $this->warningThreshold && $this->value < $this->criticalThreshold;
    }

    public function isCritical(): bool
    {
        if ($this->higherIsBetter) {
            return $this->value <= $this->criticalThreshold;
        }

        return $this->value >= $this->criticalThreshold;
    }

    public function isHealthy(): bool
    {
        return $this->success && ! $this->isWarning() && ! $this->isCritical();
    }

    public function getLevel(): string
    {
        if (! $this->success) {
            return 'error';
        }

        if ($this->isCritical()) {
            return 'critical';
        }

        if ($this->isWarning()) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'success' => $this->success,
            'value' => $this->value,
            'warning_threshold' => $this->warningThreshold,
            'critical_threshold' => $this->criticalThreshold,
            'higher_is_better' => $this->higherIsBetter,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'level' => $this->getLevel(),
            'is_healthy' => $this->isHealthy(),
        ];
    }

    public static function healthy(float $value, float $warningThreshold, float $criticalThreshold, bool $higherIsBetter = true, string $message = ''): self
    {
        return new self(
            success: true,
            value: $value,
            warningThreshold: $warningThreshold,
            criticalThreshold: $criticalThreshold,
            higherIsBetter: $higherIsBetter,
            message: $message,
        );
    }

    public static function failed(string $message, array $metadata = []): self
    {
        return new self(
            success: false,
            value: 0,
            warningThreshold: 0,
            criticalThreshold: 0,
            higherIsBetter: true,
            message: $message,
            metadata: $metadata,
        );
    }
}
