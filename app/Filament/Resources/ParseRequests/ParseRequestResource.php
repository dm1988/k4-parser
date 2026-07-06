<?php

namespace App\Filament\Resources\ParseRequests;

use App\Filament\Resources\ParseRequests\Pages\CreateParseRequest;
use App\Filament\Resources\ParseRequests\Pages\EditParseRequest;
use App\Filament\Resources\ParseRequests\Pages\ListParseRequests;
use App\Filament\Resources\ParseRequests\Schemas\ParseRequestForm;
use App\Filament\Resources\ParseRequests\Tables\ParseRequestsTable;
use App\Models\ParseRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ParseRequestResource extends Resource
{
    protected static ?string $model = ParseRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ParseRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParseRequestsTable::configure($table);
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
            'index' => ListParseRequests::route('/'),
            'create' => CreateParseRequest::route('/create'),
            'edit' => EditParseRequest::route('/{record}/edit'),
        ];
    }
}
