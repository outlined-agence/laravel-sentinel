<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Filament\Pages\V4;

use Filament\Pages\Page;
use Outlined\Sentinel\Filament\Widgets\V4\EventsByLevelChart;
use Outlined\Sentinel\Filament\Widgets\V4\EventsOverTimeChart;
use Outlined\Sentinel\Filament\Widgets\V4\RecentEventsTable;
use Outlined\Sentinel\Filament\Widgets\V4\StatsOverview;

class SentinelDashboard extends Page
{
    protected string $view = 'sentinel::filament.pages.v4.dashboard';

    protected static ?string $title = 'Monitoring Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 99;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chart-bar';
    }

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
