<?php

namespace App\Filament\Resources\CrewBidSnapshots\Tables;

use App\Models\CrewBidSnapshot;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CrewBidSnapshotsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('retrieved_at', 'desc')
            ->columns([
                TextColumn::make('period')->label('Month')->searchable()->sortable(),
                TextColumn::make('fleet')->sortable(),
                TextColumn::make('seat')->sortable(),
                TextColumn::make('retrieved_at')->label('Retrieved (UTC)')->dateTime(timezone: 'UTC')->sortable(),
                TextColumn::make('freshness')->state(fn (CrewBidSnapshot $record): string => $record->stale() ? 'Stale' : 'Current')->badge(),
                TextColumn::make('bidder_count')->label('Bidders')->state(fn (CrewBidSnapshot $record): int => count($record->bidders)),
                TextColumn::make('portal_package_id')->label('Portal package'),
                TextColumn::make('portal_revision')->label('Portal revision'),
            ])->filters([
                SelectFilter::make('fleet')->options(['747' => 'B747', '777' => 'B777']),
                SelectFilter::make('seat')->options(['CA' => 'CA', 'FO' => 'FO']),
            ])->recordActions([ViewAction::make()])
            ->emptyStateDescription('Refresh a published package’s shared bids from K4 Bids to create a validated snapshot.');
    }
}
