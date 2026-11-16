<?php

namespace App\Filament\Resources\CrewBidSnapshots\Schemas;

use App\Models\CrewBidSnapshot;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CrewBidSnapshotInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Shared bid snapshot')
                ->description('Global portal choices are shared across authorized users. Portal ranks are source evidence; personal predictions use the imported seniority roster. Refreshes replace only a complete validated month/fleet/seat snapshot.')
                ->schema([
                    TextEntry::make('period')->label('Month'),
                    TextEntry::make('fleet'),
                    TextEntry::make('seat'),
                    TextEntry::make('retrieved_at')->label('Retrieved (UTC)')->dateTime(timezone: 'UTC'),
                    TextEntry::make('freshness')->state(fn (CrewBidSnapshot $record): string => $record->stale() ? 'Stale—refresh recommended' : 'Current')->badge(),
                    TextEntry::make('portal_package_id')->label('Portal package'),
                    TextEntry::make('portal_revision')->label('Portal revision'),
                ])->columns(3)->columnSpanFull(),
        ]);
    }
}
