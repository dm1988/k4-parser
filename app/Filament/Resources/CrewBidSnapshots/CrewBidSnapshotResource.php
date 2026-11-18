<?php

namespace App\Filament\Resources\CrewBidSnapshots;

use App\Filament\Resources\CrewBidSnapshots\Pages\ListCrewBidSnapshots;
use App\Filament\Resources\CrewBidSnapshots\Pages\ViewCrewBidSnapshot;
use App\Filament\Resources\CrewBidSnapshots\Schemas\CrewBidSnapshotInfolist;
use App\Filament\Resources\CrewBidSnapshots\Tables\CrewBidSnapshotsTable;
use App\Models\CrewBidSnapshot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CrewBidSnapshotResource extends Resource
{
    protected static ?string $model = CrewBidSnapshot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'K4 Bids';

    protected static ?string $modelLabel = 'shared bid snapshot';

    protected static ?string $recordTitleAttribute = 'period';

    public static function infolist(Schema $schema): Schema
    {
        return CrewBidSnapshotInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrewBidSnapshotsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrewBidSnapshots::route('/'),
            'view' => ViewCrewBidSnapshot::route('/{record}'),
        ];
    }
}
