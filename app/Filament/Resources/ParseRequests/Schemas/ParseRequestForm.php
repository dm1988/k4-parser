<?php

namespace App\Filament\Resources\ParseRequests\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ParseRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('user_id')
                                    ->relationship('user', 'email')
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('request_uuid')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('source_type')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('parser_type')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('status')
                                    ->options([
                                        'partial' => 'Partial',
                                        'success' => 'Success',
                                        'failed' => 'Failed',
                                    ])
                                    ->required(),
                                TextInput::make('error_code')
                                    ->maxLength(255),
                            ]),
                    ]),
                Section::make('Metrics')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('parse_duration_ms')
                                    ->label('Parse duration (ms)')
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('file_size_bytes')
                                    ->label('File size (bytes)')
                                    ->integer()
                                    ->minValue(0),
                                TextInput::make('page_count')
                                    ->integer()
                                    ->minValue(0),
                                TextInput::make('detected_event_count')
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('detected_flight_count')
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('detected_hotel_count')
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                            ]),
                    ]),
                Section::make('Versions')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('file_hash')
                                    ->maxLength(64),
                                TextInput::make('app_version')
                                    ->maxLength(255),
                                TextInput::make('parser_version')
                                    ->maxLength(255),
                            ]),
                    ]),
            ]);
    }
}
