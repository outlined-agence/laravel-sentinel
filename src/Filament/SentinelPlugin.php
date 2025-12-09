<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class SentinelPlugin implements Plugin
{
    protected bool $hasResource = true;

    protected bool $hasDashboard = true;

    public static function make(): static
    {
        return new static();
    }

    public function getId(): string
    {
        return 'sentinel';
    }

    public function register(Panel $panel): void
    {
        if ($this->hasResource) {
            $panel->resources([
                $this->getResourceClass(),
            ]);
        }

        if ($this->hasDashboard) {
            $panel->pages([
                $this->getDashboardClass(),
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        // Nothing to boot
    }

    public function resource(bool $condition = true): static
    {
        $this->hasResource = $condition;

        return $this;
    }

    public function dashboard(bool $condition = true): static
    {
        $this->hasDashboard = $condition;

        return $this;
    }

    protected function getResourceClass(): string
    {
        if (FilamentVersion::isV4()) {
            return \Outlined\Sentinel\Filament\Resources\V4\SentinelEventResource::class;
        }

        return \Outlined\Sentinel\Filament\Resources\V3\SentinelEventResource::class;
    }

    protected function getDashboardClass(): string
    {
        if (FilamentVersion::isV4()) {
            return \Outlined\Sentinel\Filament\Pages\V4\SentinelDashboard::class;
        }

        return \Outlined\Sentinel\Filament\Pages\V3\SentinelDashboard::class;
    }

    protected function getWidgetClasses(): array
    {
        if (FilamentVersion::isV4()) {
            return [
                \Outlined\Sentinel\Filament\Widgets\V4\StatsOverview::class,
                \Outlined\Sentinel\Filament\Widgets\V4\EventsByLevelChart::class,
                \Outlined\Sentinel\Filament\Widgets\V4\EventsOverTimeChart::class,
                \Outlined\Sentinel\Filament\Widgets\V4\RecentEventsTable::class,
            ];
        }

        return [
            \Outlined\Sentinel\Filament\Widgets\V3\StatsOverview::class,
            \Outlined\Sentinel\Filament\Widgets\V3\EventsByLevelChart::class,
            \Outlined\Sentinel\Filament\Widgets\V3\EventsOverTimeChart::class,
            \Outlined\Sentinel\Filament\Widgets\V3\RecentEventsTable::class,
        ];
    }
}
