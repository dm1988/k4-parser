<?php

namespace App\Filament\Resources\PilotSeniorityLists\Pages;

use App\Filament\Resources\PilotSeniorityLists\PilotSeniorityListResource;
use Filament\Resources\Pages\ListRecords;

class ListPilotSeniorityLists extends ListRecords
{
    protected static string $resource = PilotSeniorityListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PilotSeniorityListResource::importAction(),
        ];
    }
}
