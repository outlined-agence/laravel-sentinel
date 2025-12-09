<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Outlined\Sentinel\Services\MonitoringService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class SentinelExceptionHandler extends ExceptionHandler
{
    /**
     * Report an exception.
     */
    public function report(Throwable $e): void
    {
        if ($this->shouldReportToSentinel($e)) {
            $this->reportToSentinel($e);
        }

        parent::report($e);
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
    protected function reportToSentinel(Throwable $e): void
    {
        try {
            $monitoring = app(MonitoringService::class);
            $monitoring->logError($e);
        } catch (Throwable) {
            // Fail silently to not break the exception handling
        }
    }
}
