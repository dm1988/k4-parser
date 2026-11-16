<?php

namespace App\Filament\Resources\PilotSeniorityLists\Tables;

use App\Models\PilotSeniorityList;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PilotSeniorityListsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('effective_date')->label('Seniority as of')->date()->sortable(),
            TextColumn::make('pilot_count')->label('Pilots')->state(fn (PilotSeniorityList $record): int => count($record->pilots)),
            TextColumn::make('imported_at')->label('Imported (UTC)')->dateTime(timezone: 'UTC')->sortable(),
            TextColumn::make('source_url')->label('Source PDF')->limit(65)->wrap(),
        ])->recordActions([ViewAction::make()])->emptyStateDescription('Import the complete dated pilot seniority PDF using its direct Kalitta link.');
    }
}
