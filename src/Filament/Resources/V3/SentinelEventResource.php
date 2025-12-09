<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Filament\Resources\V3;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Outlined\Sentinel\Filament\Resources\V3\SentinelEventResource\Pages;
use Outlined\Sentinel\Models\SentinelEvent;

class SentinelEventResource extends Resource
{
    protected static ?string $model = SentinelEvent::class;

    protected static ?string $slug = 'sentinel-events';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Events';

    protected static ?string $modelLabel = 'Event';

    protected static ?string $pluralModelLabel = 'Events';

    protected static ?int $navigationSort = 100;

    public static function getNavigationGroup(): ?string
    {
        return config('sentinel.filament.navigation_group', 'Monitoring');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Event Details')
                    ->schema([
                        Forms\Components\TextInput::make('level')
                            ->disabled(),
                        Forms\Components\TextInput::make('event_type')
                            ->disabled(),
                        Forms\Components\Textarea::make('message')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Context')
                    ->schema([
                        Forms\Components\KeyValue::make('context')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\TextInput::make('user_id')
                            ->label('User ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('ip_address')
                            ->disabled(),
                        Forms\Components\TextInput::make('environment')
                            ->disabled(),
                        Forms\Components\TextInput::make('url')
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
