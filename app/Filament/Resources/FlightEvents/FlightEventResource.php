<?php

namespace App\Filament\Resources\FlightEvents;

use App\Filament\Resources\FlightEvents\Pages\CreateFlightEvent;
use App\Filament\Resources\FlightEvents\Pages\EditFlightEvent;
use App\Filament\Resources\FlightEvents\Pages\ListFlightEvents;
use App\Filament\Resources\FlightEvents\Schemas\FlightEventForm;
use App\Filament\Resources\FlightEvents\Tables\FlightEventsTable;
use App\Models\FlightEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FlightEventResource extends Resource
{
    protected static ?string $model = FlightEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Flight Event';

    protected static ?string $pluralModelLabel = 'Flight Events';

    public static function form(Schema $schema): Schema
    {
        return FlightEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FlightEventsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlightEvents::route('/'),
            'create' => CreateFlightEvent::route('/create'),
            'edit' => EditFlightEvent::route('/{record}/edit'),
        ];
    }
}
