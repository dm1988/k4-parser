<?php

namespace App\Filament\Resources\ParseRequests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ParseRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_uuid')
                    ->label('Request')
                    ->searchable()
                    ->copyable()
                    ->limit(12)
                    ->tooltip(fn ($state): ?string => filled($state) ? $state : null),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'partial' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('User')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('parser_type')
                    ->label('Parser')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('parse_duration_ms')
                    ->label('Duration')
                    ->numeric()
                    ->suffix(' ms')
                    ->sortable(),
                TextColumn::make('detected_event_count')
                    ->label('Events')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('detected_flight_count')
                    ->label('Flights')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('detected_hotel_count')
                    ->label('Hotels')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('page_count')
                    ->label('Pages')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('file_size_bytes')
                    ->label('File Size')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error_code')
                    ->label('Error')
                    ->badge()
                    ->color('danger')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('file_hash')
                    ->label('File Hash')
                    ->searchable()
                    ->copyable()
                    ->limit(16)
                    ->tooltip(fn ($state): ?string => filled($state) ? $state : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('app_version')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('parser_version')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'partial' => 'Partial',
                        'success' => 'Success',
                        'failed' => 'Failed',
                    ]),
                SelectFilter::make('source_type')
                    ->options([
                        'pasted_text' => 'Pasted Text',
                        'pdf' => 'PDF',
                    ]),
                SelectFilter::make('parser_type')
                    ->options([
                        'roster' => 'Roster',
                        'trip_information' => 'Trip Information',
                        'published_roster' => 'Published Roster',
                    ]),
                SelectFilter::make('user')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('error_code')
                    ->label('Has Error')
                    ->nullable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
