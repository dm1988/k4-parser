<?php

namespace App\Filament\Resources\BidPackages\Schemas;

use App\Models\BidPackage;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BidPackageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Package preview')->description('Identity and contents come from the uploaded sources. Published contents stay fixed; import corrections as a new revision. Times are UTC.')
                ->schema([
                    TextEntry::make('period')->label('Bid month'),
                    TextEntry::make('fleet')->formatStateUsing(fn (string $state): string => 'B'.$state),
                    TextEntry::make('source_revision')->label('Source revision'),
                    TextEntry::make('status')->state(fn (BidPackage $record): string => $record->archived_at !== null ? 'Archived' : ($record->published_at !== null ? 'Published' : 'Draft'))->badge(),
                    TextEntry::make('importer.email')->label('Imported by')->placeholder('Deleted user'),
                    TextEntry::make('created_at')->label('Imported at (UTC)')->dateTime(timezone: 'UTC'),
                    TextEntry::make('published_at')->label('Published at (UTC)')->dateTime(timezone: 'UTC')->placeholder('Unpublished'),
                    TextEntry::make('archived_at')->label('Archived at (UTC)')->dateTime(timezone: 'UTC')->placeholder('Not archived'),
                    TextEntry::make('line_count')->label('Lines')->state(fn (BidPackage $record): int => count($record->payload['lines'] ?? [])),
                    TextEntry::make('trip_count')->label('Trips')->state(fn (BidPackage $record): int => count($record->payload['trips'] ?? [])),
                    TextEntry::make('workspace_count')->label('Saved workspaces')->state(fn (BidPackage $record): int => $record->workspaces()->count()),
                ])->columns(3)->columnSpanFull(),
            Section::make('Source coverage')->description('Missing sources can leave details unavailable. Review coverage before publishing. Sources are not retained after parsing.')
                ->schema(array_map(fn (string $source): TextEntry => TextEntry::make('coverage_'.$source)
                    ->label(ucfirst($source))
                    ->state(fn (BidPackage $record): string => ! isset($record->payload['coverage'][$source]) ? 'Not recorded' : ($record->payload['coverage'][$source] ? 'Imported' : 'Not imported')), ['overview', 'lines', 'trips']))
                ->columns(3)->columnSpanFull(),
        ]);
    }
}
