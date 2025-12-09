<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Filament\Resources\V4\SentinelEventResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Outlined\Sentinel\Filament\Resources\V4\SentinelEventResource;

class ListSentinelEvents extends ListRecords
{
    protected static string $resource = SentinelEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
