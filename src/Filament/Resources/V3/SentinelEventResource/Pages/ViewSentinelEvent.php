<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Filament\Resources\V3\SentinelEventResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Outlined\Sentinel\Filament\Resources\V3\SentinelEventResource;

class ViewSentinelEvent extends ViewRecord
{
    protected static string $resource = SentinelEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
