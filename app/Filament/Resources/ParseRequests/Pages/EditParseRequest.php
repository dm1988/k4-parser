<?php

namespace App\Filament\Resources\ParseRequests\Pages;

use App\Filament\Resources\ParseRequests\ParseRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditParseRequest extends EditRecord
{
    protected static string $resource = ParseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
