<?php

namespace App\Filament\Resources\FlightEvents\Pages;

use App\Filament\Resources\FlightEvents\FlightEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFlightEvent extends EditRecord
{
    protected static string $resource = FlightEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
