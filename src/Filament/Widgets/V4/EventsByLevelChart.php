<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Filament\Widgets\V4;

use Filament\Widgets\ChartWidget;
use Outlined\Sentinel\Models\SentinelEvent;

class EventsByLevelChart extends ChartWidget
{
    protected ?string $heading = 'Events by Level (Last 24h)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        $colors = [
            '#808080', // debug - gray
            '#3498db', // info - blue
            '#2ecc71', // notice - green
            '#f39c12', // warning - orange
            '#e74c3c', // error - red
            '#9b59b6', // critical - purple
            '#e91e63', // alert - pink
            '#000000', // emergency - black
        ];

        $data = [];
        foreach ($levels as $level) {
            $data[] = SentinelEvent::lastHours(24)->level($level)->count();
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => array_map('ucfirst', $levels),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
