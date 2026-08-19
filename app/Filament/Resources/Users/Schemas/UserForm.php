<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
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
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->required()
                    ->disabled(fn (?User $record): bool => $record?->getKey() === auth()->id()),
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
