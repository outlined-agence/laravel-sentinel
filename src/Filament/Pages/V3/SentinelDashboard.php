<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Filament\Pages\V3;

use Filament\Pages\Page;
use Outlined\Sentinel\Filament\Widgets\V3\EventsByLevelChart;
use Outlined\Sentinel\Filament\Widgets\V3\EventsOverTimeChart;
use Outlined\Sentinel\Filament\Widgets\V3\RecentEventsTable;
use Outlined\Sentinel\Filament\Widgets\V3\StatsOverview;

class SentinelDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'sentinel::filament.pages.dashboard';

    protected static ?string $title = 'Monitoring Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 99;

    public static function getNavigationGroup(): ?string
    {
        return config('sentinel.filament.navigation_group', 'Monitoring');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            EventsOverTimeChart::class,
            EventsByLevelChart::class,
            RecentEventsTable::class,
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('sentinel.database.enabled', false);
    }
}
