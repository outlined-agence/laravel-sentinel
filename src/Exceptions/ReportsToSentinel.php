<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Exceptions;

use Outlined\Sentinel\Services\MonitoringService;
use Throwable;

/**
 * Trait to add Sentinel reporting to existing exception handlers.
 *
 * Usage:
 * ```php
 * use Outlined\Sentinel\Exceptions\ReportsToSentinel;
 *
 * class Handler extends ExceptionHandler
 * {
 *     use ReportsToSentinel;
 *
 *     public function report(Throwable $e): void
 *     {
 *         $this->reportToSentinelIfNeeded($e);
 *         parent::report($e);
 *     }
 * }
 * ```
 */
trait ReportsToSentinel
{
    /**
     * Report the exception to Sentinel if it should be reported.
     */
    protected function reportToSentinelIfNeeded(Throwable $e): void
    {
        if (SentinelExceptionFilter::shouldReport($e)) {
            try {
                $monitoring = app(MonitoringService::class);
                $monitoring->logError($e);
            } catch (Throwable) {
                // Fail silently to not break the exception handling
            }
        }
    }
}
