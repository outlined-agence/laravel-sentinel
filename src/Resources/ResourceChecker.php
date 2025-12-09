<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Resources;

use Outlined\Sentinel\Contracts\MonitorableResource;
use Outlined\Sentinel\Services\MonitoringService;
use Throwable;

class ResourceChecker
{
    /**
     * @var array<MonitorableResource>
     */
    protected array $resources = [];

    public function __construct(
        protected MonitoringService $monitoring,
    ) {}

    /**
     * Register a resource to monitor.
     */
    public function register(MonitorableResource $resource): void
    {
        $this->resources[$resource->getIdentifier()] = $resource;
    }

    /**
     * Register resources from config.
     *
     * @param  array<class-string<MonitorableResource>>  $resourceClasses
     */
    public function registerFromConfig(array $resourceClasses): void
    {
        foreach ($resourceClasses as $class) {
            if (class_exists($class)) {
                $resource = app($class);
                if ($resource instanceof MonitorableResource) {
                    $this->register($resource);
                }
            }
        }
    }

    /**
     * Check all registered resources and alert if necessary.
     *
     * @return array<string, ResourceStatus>
     */
    public function checkAll(): array
    {
        $results = [];

        foreach ($this->resources as $identifier => $resource) {
            $results[$identifier] = $this->check($resource);
        }

        return $results;
    }

    /**
     * Check a single resource and alert if necessary.
     */
    public function check(MonitorableResource $resource): ResourceStatus
    {
        try {
            $status = $resource->check();

            // Alert if not healthy
            if (! $status->isHealthy()) {
                $this->alert($resource, $status);
            }

            return $status;
        } catch (Throwable $e) {
            $status = ResourceStatus::failed(
                message: "Failed to check resource: {$e->getMessage()}",
                metadata: ['exception' => get_class($e)],
            );

            $this->monitoring->logError($e, null, [
                'resource' => $resource->getIdentifier(),
                'resource_name' => $resource->getName(),
            ]);

            return $status;
        }
    }

    /**
     * Check a resource by its identifier.
     */
    public function checkByIdentifier(string $identifier): ?ResourceStatus
    {
        $resource = $this->resources[$identifier] ?? null;

        if ($resource === null) {
            return null;
        }

        return $this->check($resource);
    }

    /**
     * Send alert for a resource status.
     */
    protected function alert(MonitorableResource $resource, ResourceStatus $status): void
    {
        $message = $status->message ?: $this->buildAlertMessage($resource, $status);

        $this->monitoring->logThresholdAlert(
            type: $resource->getIdentifier(),
            message: $message,
            currentValue: $status->value,
            threshold: $status->isCritical() ? $status->criticalThreshold : $status->warningThreshold,
            critical: $status->isCritical(),
        );
    }

    /**
     * Build a default alert message.
     */
    protected function buildAlertMessage(MonitorableResource $resource, ResourceStatus $status): string
    {
        $level = $status->isCritical() ? 'CRITICAL' : 'WARNING';
        $name = $resource->getName();
        $value = $status->value;
        $threshold = $status->isCritical() ? $status->criticalThreshold : $status->warningThreshold;
        $direction = $resource->higherIsBetter() ? 'below' : 'above';

        return "[{$level}] {$name}: Current value ({$value}) is {$direction} threshold ({$threshold})";
    }

    /**
     * Get all registered resources.
     *
     * @return array<string, MonitorableResource>
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * Get a resource by identifier.
     */
    public function getResource(string $identifier): ?MonitorableResource
    {
        return $this->resources[$identifier] ?? null;
    }

    /**
     * Check if a resource is registered.
     */
    public function hasResource(string $identifier): bool
    {
        return isset($this->resources[$identifier]);
    }

    /**
     * Remove a resource from monitoring.
     */
    public function unregister(string $identifier): void
    {
        unset($this->resources[$identifier]);
    }

    /**
     * Get status of all resources without alerting.
     *
     * @return array<string, ResourceStatus>
     */
    public function status(): array
    {
        $results = [];

        foreach ($this->resources as $identifier => $resource) {
            try {
                $results[$identifier] = $resource->check();
            } catch (Throwable $e) {
                $results[$identifier] = ResourceStatus::failed(
                    message: "Check failed: {$e->getMessage()}",
                );
            }
        }

        return $results;
    }
}
