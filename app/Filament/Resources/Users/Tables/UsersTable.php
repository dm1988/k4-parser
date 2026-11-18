<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('role')->badge()->sortable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('crew_bids_access')
                    ->label('K4 Bids grant')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('crew_bid_employee_number')->label('Employee number')->placeholder('Not linked')->toggleable(),
                TextColumn::make('crew_bid_employee_verified_at')->label('Employee verified (UTC)')->dateTime(timezone: 'UTC')->placeholder('Not verified')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('has_bought_coffee')
                    ->label('Bought coffee')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stripe_id')
                    ->searchable(),
                TextColumn::make('pm_type')
                    ->searchable(),
                TextColumn::make('pm_last_four')
                    ->searchable(),
                TextColumn::make('trial_ends_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                UserResource::unlinkEmployeeAction(),
                DeleteAction::make()
                    ->modalHeading('Delete user')
                    ->modalDescription(fn (User $record): string => "Are you sure you want to delete {$record->name} ({$record->email})? This action cannot be undone.")
                    ->modalSubmitActionLabel('Delete user'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
