<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = User::query()->findOrFail($this->getRecord()->getKey());
        Gate::authorize('update', $record);
        if (! UserResource::canChangeRole($record)) {
            unset($data['role']);
        }
        unset($data['crew_bid_employee_number'], $data['crew_bid_employee_verified_at']);
        if ($this->getRecord()->getKey() === auth()->id()) {
            unset($data['is_active']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            UserResource::unlinkEmployeeAction(),
            DeleteAction::make()
                ->modalHeading('Delete user')
                ->modalDescription(fn (User $record): string => "Are you sure you want to delete {$record->name} ({$record->email})? This action cannot be undone.")
                ->modalSubmitActionLabel('Delete user'),
        ];
    }
}
