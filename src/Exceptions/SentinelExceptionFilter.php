<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class SentinelExceptionFilter
{
    /**
     * Determine if the exception should be reported to Sentinel.
     */
    public static function shouldReport(Throwable $e): bool
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
}
