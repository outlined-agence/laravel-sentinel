<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Outlined\Sentinel\Contracts\ContextExtractor;
use Throwable;

class ContextBuilder
{
    /**
     * @var array<ContextExtractor>
     */
    protected array $extractors = [];

    public function __construct(
        protected ?Request $request = null,
    ) {}

    /**
     * @param  array<class-string<ContextExtractor>>  $extractorClasses
     */
    public function registerExtractors(array $extractorClasses): void
    {
        foreach ($extractorClasses as $class) {
            if (class_exists($class)) {
                $this->extractors[] = app($class);
            }
        }

        // Sort by priority (highest first)
        usort($this->extractors, fn (ContextExtractor $a, ContextExtractor $b) => $b->priority() <=> $a->priority());
    }

    /**
     * Build the full context for a monitoring event.
     *
     * @param  array<string, mixed>  $additionalContext
     * @return array<string, mixed>
     */
    public function build(
        string $eventType,
        array $additionalContext = [],
        ?Throwable $exception = null,
        ?Model $user = null,
    ): array {
        $context = [
            'environment' => $this->getEnvironmentContext(),
            'request' => $this->getRequestContext(),
            'user' => $this->getUserContext($user),
            'timestamp' => now()->toIso8601String(),
            'event_type' => $eventType,
        ];

        if ($exception !== null) {
            $context['exception'] = $this->getExceptionContext($exception);
        }

        // Merge additional context
        $context = array_merge($context, ['data' => $additionalContext]);

        // Run custom extractors
        foreach ($this->extractors as $extractor) {
            if ($extractor->shouldExtract($eventType, $context, $exception)) {
                $extracted = $extractor->extract($eventType, $context, $exception);
                $context = array_merge($context, $extracted);
            }
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getEnvironmentContext(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getRequestContext(): array
    {
        if ($this->request === null) {
            return [];
        }

        return [
            'url' => $this->request->fullUrl(),
            'method' => $this->request->method(),
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'route' => $this->request->route()?->getName() ?? $this->request->path(),
            'referrer' => $this->request->header('referer'),
            'ajax' => $this->request->ajax(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUserContext(?Model $user = null): array
    {
        $user = $user ?? $this->getAuthenticatedUser();

        if ($user === null) {
            return [];
        }

        $context = [
            'id' => $user->getKey(),
        ];

        // Common user attributes
        $commonAttributes = ['email', 'name', 'status', 'role', 'type'];
        foreach ($commonAttributes as $attribute) {
            if (isset($user->{$attribute})) {
                $context[$attribute] = $user->{$attribute};
            }
        }

        return $context;
    }

    protected function getAuthenticatedUser(): ?Authenticatable
    {
        try {
            return auth()->user();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getExceptionContext(Throwable $exception, int $depth = 0): array
    {
        $maxPreviousDepth = 3;
        $trace = $this->getStackTrace($exception);

        $previous = null;
        if ($exception->getPrevious() && $depth < $maxPreviousDepth) {
            $previous = $this->getExceptionContext($exception->getPrevious(), $depth + 1);
        }

        return [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $trace,
            'previous' => $previous,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getStackTrace(Throwable $exception): array
    {
        $maxLines = config('sentinel.formatting.stack_trace_lines', 10);
        $trace = [];

        foreach (array_slice($exception->getTrace(), 0, $maxLines) as $frame) {
            $trace[] = [
                'file' => Arr::get($frame, 'file', '[internal]'),
                'line' => Arr::get($frame, 'line', 0),
                'function' => Arr::get($frame, 'function', ''),
                'class' => Arr::get($frame, 'class', ''),
            ];
        }

        return $trace;
    }

    /**
     * Get the calling context (file, line, function).
     *
     * @return array<string, mixed>
     */
    public function getCallingContext(int $depth = 3): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 1);
        $frame = $trace[$depth] ?? [];

        return [
            'file' => Arr::get($frame, 'file', ''),
            'line' => Arr::get($frame, 'line', 0),
            'function' => Arr::get($frame, 'function', ''),
            'class' => Arr::get($frame, 'class', ''),
        ];
    }

    /**
     * Limit the depth of nested arrays in context.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function limitDepth(array $data, int $maxDepth = 3, int $currentDepth = 0): array
    {
        if ($currentDepth >= $maxDepth) {
            return ['[truncated]'];
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->limitDepth($value, $maxDepth, $currentDepth + 1);
            } elseif (is_object($value)) {
                $result[$key] = '[object: ' . get_class($value) . ']';
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
