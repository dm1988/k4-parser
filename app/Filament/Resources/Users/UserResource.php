<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function canChangeRole(User $record): bool
    {
        return Gate::allows('update', $record) && $record->id !== Auth::id() && $record->role !== 'super_admin';
    }

    public static function unlinkEmployeeAction(): Action
    {
        return Action::make('unlinkEmployee')
            ->label('Unlink employee number')
            ->authorize('update')
            ->visible(fn (User $record): bool => $record->crew_bid_employee_number !== null || $record->crew_bid_employee_verified_at !== null)
            ->requiresConfirmation()
            ->modalDescription('Remove this account’s verified Kalitta identity. Personal predictions will be unavailable until the user verifies their own employee number again. Saved bids and shared data are preserved.')
            ->action(function (User $record, Page $livewire): void {
                Gate::authorize('update', $record);
                $record->forceFill(['crew_bid_employee_number' => null, 'crew_bid_employee_verified_at' => null])->save();
                if ($livewire instanceof EditUser) {
                    $livewire->refreshFormData(['crew_bid_employee_number', 'crew_bid_employee_verified_at']);
                }
                Notification::make()->success()->title('Employee link removed')->send();
            });
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
