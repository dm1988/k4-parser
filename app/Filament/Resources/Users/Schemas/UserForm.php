<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                DateTimePicker::make('email_verified_at'),
                Select::make('role')
                    ->options(fn (?User $record): array => $record?->role === 'super_admin'
                        ? ['super_admin' => 'Super admin'] : ['user' => 'User', 'admin' => 'Admin'])
                    ->required()
                    ->disabled(fn (?User $record): bool => $record === null || ! UserResource::canChangeRole($record))
                    ->helperText('Admins can change other users between User and Admin. Your own role and super-admin roles remain protected.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->required()
                    ->disabled(fn (?User $record): bool => $record?->getKey() === auth()->id()),
                Toggle::make('crew_bids_access')
                    ->label('K4 Bids access')
                    ->helperText('Grants K4 Bids to a regular user. Admins already have access; only admins can manage packages.')
                    ->default(false),
                TextInput::make('crew_bid_employee_number')
                    ->label('Verified employee number')
                    ->formatStateUsing(fn (?User $record): ?string => $record?->crew_bid_employee_number)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('The user verifies this number with their own Kalitta login. Unlink it to allow a fresh verification.'),
                DateTimePicker::make('crew_bid_employee_verified_at')
                    ->label('Employee verified at (UTC)')
                    ->timezone('UTC')
                    ->disabled()
                    ->dehydrated(false),
                Toggle::make('has_bought_coffee')
                    ->label('Bought a coffee')
                    ->default(false),
                TextInput::make('password')
                    ->password()
                    ->rule(Password::default())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->same('password_confirmation'),
                TextInput::make('password_confirmation')
                    ->password()
                    ->required(fn (Get $get): bool => filled($get('password')))
                    ->visible(fn (Get $get): bool => filled($get('password')))
                    ->dehydrated(false),
            ]);
    }
}
