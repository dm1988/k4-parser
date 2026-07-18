<?php

namespace App\Filament\Resources\FlightEvents\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class FlightEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Select::make('type')
                                    ->options([
                                        'flight' => 'Flight',
                                        'deadhead' => 'Deadhead',
                                        'duty' => 'Duty',
                                    ])
                                    ->searchable()
                                    ->required(),
                                TextInput::make('status')
                                    ->maxLength(255),
                                DateTimePicker::make('start')
                                    ->required()
                                    ->seconds(false),
                                DateTimePicker::make('end')
                                    ->required()
                                    ->seconds(false),
                                TextInput::make('timezone')
                                    ->maxLength(255)
                                    ->default('UTC'),
                                Toggle::make('is_deadhead')
                                    ->required(),
                            ]),
                    ]),
                Section::make('Flight Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('flight_number')
                                    ->maxLength(255),
                                TextInput::make('trip_id')
                                    ->maxLength(255),
                                TextInput::make('origin')
                                    ->maxLength(3),
                                TextInput::make('destination')
                                    ->maxLength(3),
                                Select::make('aircraft_id')
                                    ->relationship('aircraft', 'tail_number')
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('tail_number')
                                    ->label('Fallback Tail Number')
                                    ->helperText('Used only when no aircraft record is selected.')
                                    ->disabled(fn (Get $get): bool => filled($get('aircraft_id')))
                                    ->maxLength(255),
                            ]),
                    ]),
                Section::make('Display Metadata')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('type_label')
                                    ->maxLength(255),
                                TextInput::make('schedule_label')
                                    ->maxLength(255),
                                TextInput::make('duration_label')
                                    ->maxLength(255),
                                TextInput::make('badge_color')
                                    ->maxLength(255),
                                TextInput::make('type_icon')
                                    ->maxLength(255),
                                Textarea::make('type_description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Section::make('Downloads & Metadata')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('download_url')
                                    ->url()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('download_id')
                                    ->maxLength(255),
                                KeyValue::make('metadata')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
