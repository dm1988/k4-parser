<?php

namespace App\Filament\Resources\Aircraft\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AircraftTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tail_number')
                    ->searchable(),
                TextColumn::make('manufacturer')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('model')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('airline')
                    ->searchable(),
                TextColumn::make('max_zero_fuel_weight')
                    ->label('MZFW')
                    ->numeric()
                    ->suffix(' lb')
                    ->sortable(),
                TextColumn::make('max_takeoff_weight')
                    ->label('MTOW')
                    ->numeric()
                    ->suffix(' lb')
                    ->sortable(),
                TextColumn::make('max_landing_weight')
                    ->label('MLW')
                    ->numeric()
                    ->suffix(' lb')
                    ->sortable(),
                TextColumn::make('minimum_flight_weight')
                    ->label('Minimum Flight Weight')
                    ->numeric()
                    ->suffix(' lb')
                    ->sortable(),
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
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
