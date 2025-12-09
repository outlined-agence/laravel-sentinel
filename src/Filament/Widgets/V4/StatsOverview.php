<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Filament\Widgets\V4;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Outlined\Sentinel\Models\SentinelEvent;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $todayTotal = SentinelEvent::today()->count();
        $todayErrors = SentinelEvent::today()->errors()->count();
        $lastHourTotal = SentinelEvent::lastHours(1)->count();
        $lastHourErrors = SentinelEvent::lastHours(1)->errors()->count();

        // Calculate trends
        $yesterdayTotal = SentinelEvent::whereDate('created_at', today()->subDay())->count();
        $yesterdayErrors = SentinelEvent::whereDate('created_at', today()->subDay())->errors()->count();

        $totalTrend = $yesterdayTotal > 0 ? round((($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100) : 0;
        $errorTrend = $yesterdayErrors > 0 ? round((($todayErrors - $yesterdayErrors) / $yesterdayErrors) * 100) : 0;

        return [
            Stat::make('Events Today', $todayTotal)
                ->description($this->getTrendDescription($totalTrend, 'vs yesterday'))
                ->descriptionIcon($this->getTrendIcon($totalTrend))
                ->color($this->getTrendColor($totalTrend, true)),

            Stat::make('Errors Today', $todayErrors)
                ->description($this->getTrendDescription($errorTrend, 'vs yesterday'))
                ->descriptionIcon($this->getTrendIcon($errorTrend))
                ->color($todayErrors > 0 ? 'danger' : 'success'),

            Stat::make('Last Hour', $lastHourTotal)
                ->description("{$lastHourErrors} errors")
                ->descriptionIcon($lastHourErrors > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($lastHourErrors > 0 ? 'warning' : 'success'),

            Stat::make('Total Events', SentinelEvent::count())
                ->description('All time')
                ->color('gray'),
        ];
    }

    protected function getTrendDescription(int $trend, string $suffix): string
    {
        if ($trend === 0) {
            return "No change {$suffix}";
        }

        $direction = $trend > 0 ? '+' : '';

        return "{$direction}{$trend}% {$suffix}";
    }

    protected function getTrendIcon(int $trend): string
    {
        if ($trend > 0) {
            return 'heroicon-m-arrow-trending-up';
        }

        if ($trend < 0) {
            return 'heroicon-m-arrow-trending-down';
        }

        return 'heroicon-m-minus';
    }

    protected function getTrendColor(int $trend, bool $lowerIsBetter = false): string
    {
        if ($trend === 0) {
            return 'gray';
        }

        $isPositive = $trend > 0;

        if ($lowerIsBetter) {
            return $isPositive ? 'warning' : 'success';
        }

        return $isPositive ? 'success' : 'danger';
    }
}
