<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Filament\Widgets\V4;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Outlined\Sentinel\Models\SentinelEvent;

class RecentEventsTable extends BaseWidget
{
    protected static ?string $heading = 'Recent Events';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SentinelEvent::query()
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'debug' => 'gray',
                        'info' => 'info',
                        'notice' => 'success',
                        'warning' => 'warning',
                        'error', 'critical', 'alert', 'emergency' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('event_type')
                    ->label('Type'),

                TextColumn::make('message')
                    ->limit(60),

                TextColumn::make('environment')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'production' => 'danger',
                        'staging' => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('created_at')
                    ->since(),
            ])
            ->actions([
                Action::make('view')
                    ->url(fn (SentinelEvent $record): string => route('filament.admin.resources.sentinel-events.view', $record))
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated(false)
            ->poll('30s');
    }
}
