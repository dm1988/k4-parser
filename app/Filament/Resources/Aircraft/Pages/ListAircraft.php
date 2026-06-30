<?php

namespace App\Filament\Resources\Aircraft\Pages;

use App\Filament\Resources\Aircraft\AircraftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAircraft extends ListRecords
{
    protected static string $resource = AircraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
