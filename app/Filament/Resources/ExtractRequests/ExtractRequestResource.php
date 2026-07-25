<?php

namespace App\Filament\Resources\ExtractRequests;

use App\Filament\Resources\ExtractRequests\Pages\CreateExtractRequest;
use App\Filament\Resources\ExtractRequests\Pages\EditExtractRequest;
use App\Filament\Resources\ExtractRequests\Pages\ListExtractRequests;
use App\Filament\Resources\ExtractRequests\Schemas\ExtractRequestForm;
use App\Filament\Resources\ExtractRequests\Tables\ExtractRequestsTable;
use App\Models\ExtractRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExtractRequestResource extends Resource
{
    protected static ?string $model = ExtractRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    public static function form(Schema $schema): Schema
    {
        return ExtractRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExtractRequestsTable::configure($table);
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
            'index' => ListExtractRequests::route('/'),
            'create' => CreateExtractRequest::route('/create'),
            'edit' => EditExtractRequest::route('/{record}/edit'),
        ];
    }
}
