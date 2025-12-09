<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Filament\Resources\V4;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Outlined\Sentinel\Filament\Resources\V4\SentinelEventResource\Pages;
use Outlined\Sentinel\Models\SentinelEvent;

class SentinelEventResource extends Resource
{
    protected static ?string $model = SentinelEvent::class;

    protected static ?string $slug = 'sentinel-events';

    protected static ?string $navigationLabel = 'Events';

    protected static ?string $modelLabel = 'Event';

    protected static ?string $pluralModelLabel = 'Events';

    protected static ?int $navigationSort = 100;

    public static function getNavigationGroup(): ?string
    {
        return config('sentinel.filament.navigation_group', 'Monitoring');
    }

    public static function getNavigationIcon(): string
    {
        return config('sentinel.filament.navigation_icon', 'heroicon-o-shield-check');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->components([
                Section::make('Event Details')
                    ->schema([
                        TextInput::make('level')
                            ->disabled(),
                        TextInput::make('event_type')
                            ->disabled(),
                        Textarea::make('message')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Context')
                    ->schema([
                        KeyValue::make('context')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Section::make('Metadata')
                    ->schema([
                        TextInput::make('user_id')
                            ->label('User ID')
                            ->disabled(),
                        TextInput::make('ip_address')
                            ->disabled(),
                        TextInput::make('environment')
                            ->disabled(),
                        TextInput::make('url')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'debug' => 'gray',
                        'info' => 'info',
                        'notice' => 'success',
                        'warning' => 'warning',
                        'error' => 'danger',
                        'critical' => 'danger',
                        'alert' => 'danger',
                        'emergency' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Type')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('message')
                    ->limit(50)
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('environment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'production' => 'danger',
                        'staging' => 'warning',
                        'local' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('user_id')
                    ->label('User')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('level')
                    ->options([
                        'debug' => 'Debug',
                        'info' => 'Info',
                        'notice' => 'Notice',
                        'warning' => 'Warning',
                        'error' => 'Error',
                        'critical' => 'Critical',
                        'alert' => 'Alert',
                        'emergency' => 'Emergency',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('environment')
                    ->options(fn () => SentinelEvent::distinct()
                        ->pluck('environment', 'environment')
                        ->filter()
                        ->toArray()
                    ),

                Tables\Filters\Filter::make('errors_only')
                    ->query(fn (Builder $query): Builder => $query->whereIn('level', ['error', 'critical', 'alert', 'emergency']))
                    ->label('Errors Only'),

                Tables\Filters\Filter::make('today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today()))
                    ->label('Today'),

                Tables\Filters\Filter::make('last_hour')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subHour()))
                    ->label('Last Hour'),
            ])
            ->actions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSentinelEvents::route('/'),
            'view' => Pages\ViewSentinelEvent::route('/{record}'),
        ];
    }
}
