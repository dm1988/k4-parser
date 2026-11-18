<?php

namespace App\Filament\Resources\PilotSeniorityLists\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PilotSeniorityListInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Current pilot seniority list')
                ->description('Updated only by a manual validated PDF import. Employee numbers identify pilots across changes to names and ranks.')
                ->schema([
                    TextEntry::make('effective_date')->label('Seniority as of')->date(),
                    TextEntry::make('imported_at')->label('Imported (UTC)')->dateTime(timezone: 'UTC'),
                    TextEntry::make('imported_by')->label('Importing admin ID')->placeholder('Deleted user'),
                    TextEntry::make('source_url')->label('Source PDF')->columnSpanFull(),
                    TextEntry::make('fingerprint')->label('Source fingerprint')->columnSpanFull(),
                ])->columns(3)->columnSpanFull(),
        ]);
    }
}
