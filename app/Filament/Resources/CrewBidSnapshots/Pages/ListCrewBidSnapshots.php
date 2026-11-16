<?php

namespace App\Filament\Resources\CrewBidSnapshots\Pages;

use App\Filament\Resources\CrewBidSnapshots\CrewBidSnapshotResource;
use Filament\Resources\Pages\ListRecords;

class ListCrewBidSnapshots extends ListRecords
{
    protected static string $resource = CrewBidSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
