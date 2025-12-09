<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Filament\Widgets\V3;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Outlined\Sentinel\Models\SentinelEvent;

class EventsOverTimeChart extends ChartWidget
{
    protected static ?string $heading = 'Events Over Time';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    protected static ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $days = 7;
        $labels = [];
        $allEvents = [];
        $errors = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('M d');

            $allEvents[] = SentinelEvent::whereDate('created_at', $date)->count();
            $errors[] = SentinelEvent::whereDate('created_at', $date)->errors()->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'All Events',
                    'data' => $allEvents,
                    'borderColor' => '#3498db',
                    'backgroundColor' => 'rgba(52, 152, 219, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Errors',
                    'data' => $errors,
                    'borderColor' => '#e74c3c',
                    'backgroundColor' => 'rgba(231, 76, 60, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
