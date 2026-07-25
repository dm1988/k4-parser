<?php

namespace App\Filament\Resources\ExtractRequests\Pages;

use App\Filament\Resources\ExtractRequests\ExtractRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExtractRequest extends EditRecord
{
    protected static string $resource = ExtractRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
