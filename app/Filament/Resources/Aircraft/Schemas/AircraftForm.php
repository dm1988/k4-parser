<?php

namespace App\Filament\Resources\Aircraft\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AircraftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tail_number')
                    ->required(),
                TextInput::make('manufacturer'),
                TextInput::make('type'),
                TextInput::make('model'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('airline'),
                TextInput::make('max_zero_fuel_weight')
                    ->label('Maximum Zero Fuel Weight')
                    ->integer()
                    ->minValue(0)
                    ->suffix('lb'),
                TextInput::make('max_takeoff_weight')
                    ->label('Maximum Takeoff Weight')
                    ->integer()
                    ->minValue(0)
                    ->suffix('lb'),
                TextInput::make('max_landing_weight')
                    ->label('Maximum Landing Weight')
                    ->integer()
                    ->minValue(0)
                    ->suffix('lb'),
                TextInput::make('minimum_flight_weight')
                    ->label('Minimum Flight Weight')
                    ->integer()
                    ->minValue(0)
                    ->suffix('lb'),
            ]);
    }
}
