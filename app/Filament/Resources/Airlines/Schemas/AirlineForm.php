<?php

namespace App\Filament\Resources\Airlines\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AirlineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('iata_code'),
                TextInput::make('icao_code'),
                TextInput::make('callsign'),
                TextInput::make('country'),
                Toggle::make('active')
                    ->required(),
            ]);
    }
}
