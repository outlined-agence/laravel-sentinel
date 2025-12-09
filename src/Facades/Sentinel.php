<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Outlined\Sentinel\Services\MonitoringService;
use Throwable;

/**
 * @method static void logError(Throwable $exception, ?Model $user = null, array $additionalContext = [])
 * @method static void logBusinessEvent(string $type, bool $success, string $message, array $additionalContext = [])
 * @method static void logProviderError(string $provider, string $message, array $data = [])
 * @method static void logThresholdAlert(string $type, string $message, float $currentValue, float $threshold, bool $critical = false)
 * @method static void log(string $level, string $message, array $context = [])
 * @method static bool isEnabled()
 * @method static void disable()
 * @method static void enable()
 * @method static mixed withoutMonitoring(callable $callback)
 *
 * @see \Outlined\Sentinel\Services\MonitoringService
 */
class Sentinel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MonitoringService::class;
    }
}
