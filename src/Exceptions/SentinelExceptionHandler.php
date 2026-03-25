<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Outlined\Sentinel\Services\MonitoringService;
use Throwable;

class SentinelExceptionHandler extends ExceptionHandler
{
    /**
     * Report an exception.
     */
    public function report(Throwable $e): void
    {
        if (SentinelExceptionFilter::shouldReport($e)) {
            try {
                $monitoring = app(MonitoringService::class);
                $monitoring->logError($e);
            } catch (Throwable) {
                // Fail silently to not break the exception handling
            }
        }

        parent::report($e);
    }
}
