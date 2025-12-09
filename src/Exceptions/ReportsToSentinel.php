<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Exceptions;

use Outlined\Sentinel\Services\MonitoringService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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
        if ($this->shouldReportToSentinel($e)) {
            $this->reportExceptionToSentinel($e);
        }
    }

    /**
     * Determine if the exception should be reported to Sentinel.
     */
    protected function shouldReportToSentinel(Throwable $e): bool
    {
        // Check if Sentinel is enabled
        if (! config('sentinel.enabled', true)) {
            return false;
        }

        // Check ignore list
        $ignoreExceptions = config('sentinel.ignore_exceptions', []);
        foreach ($ignoreExceptions as $type) {
            if ($e instanceof $type) {
                return false;
            }
        }

        // Check HTTP status codes
        if ($e instanceof HttpExceptionInterface) {
            $statusCode = $e->getStatusCode();
            $ignoreCodes = config('sentinel.ignore_http_codes', []);

            if (in_array($statusCode, $ignoreCodes, true)) {
                return false;
            }

            // Only report 5xx errors by default
            if ($statusCode < 500) {
                return false;
            }
        }

        return true;
    }

    /**
     * Report the exception to Sentinel.
     */
    protected function reportExceptionToSentinel(Throwable $e): void
    {
        try {
            $monitoring = app(MonitoringService::class);
            $monitoring->logError($e);
        } catch (Throwable) {
            // Fail silently to not break the exception handling
        }
    }
}
