<?php

namespace App\Filament\Resources\FlightEvents\Pages;

use App\Filament\Resources\FlightEvents\FlightEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlightEvents extends ListRecords
{
    protected static string $resource = FlightEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
