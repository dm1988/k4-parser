<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalHeading('Delete user')
                ->modalDescription(fn (User $record): string => "Are you sure you want to delete {$record->name} ({$record->email})? This action cannot be undone.")
                ->modalSubmitActionLabel('Delete user'),
        ];
    }
}
