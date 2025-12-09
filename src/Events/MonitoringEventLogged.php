<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MonitoringEventLogged
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $level,
        public readonly string $message,
        public readonly array $context = [],
    ) {}
}
