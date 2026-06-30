<?php

namespace App\Filament\Resources\FlightEvents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FlightEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('flight_number')
                    ->label('Flight')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('schedule_label')
                    ->label('Route')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('origin')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('destination')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('aircraft.tail_number')
                    ->label('Aircraft')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tail_number')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_deadhead')
                    ->boolean(),
                TextColumn::make('start')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('duration_label')
                    ->label('Planned Duration')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('duration')
                    ->label('Actual Duration'),
                TextColumn::make('trip_id')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('timezone')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'flight' => 'Flight',
                        'deadhead' => 'Deadhead',
                        'duty' => 'Duty',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'boarding' => 'Boarding',
                        'completed' => 'Completed',
                        'delayed' => 'Delayed',
                    ]),
                SelectFilter::make('aircraft')
                    ->relationship('aircraft', 'tail_number')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_deadhead')
                    ->label('Deadhead'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start', 'desc');
    }
}
