<?php

namespace App\Filament\Resources\ExtractRequests\Pages;

use App\Filament\Resources\ExtractRequests\ExtractRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExtractRequests extends ListRecords
{
    protected static string $resource = ExtractRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
