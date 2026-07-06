<?php

namespace App\Filament\Resources\ParseRequests\Pages;

use App\Filament\Resources\ParseRequests\ParseRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListParseRequests extends ListRecords
{
    protected static string $resource = ParseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
