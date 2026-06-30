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
            ]);
    }
}
