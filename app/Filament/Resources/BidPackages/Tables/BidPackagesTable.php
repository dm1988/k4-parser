<?php

namespace App\Filament\Resources\BidPackages\Tables;

use App\Models\BidPackage;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BidPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('importer'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('period')->orderByDesc('id'))
            ->columns([
                TextColumn::make('period')->label('Month')->searchable()->sortable(),
                TextColumn::make('fleet')->formatStateUsing(fn (string $state): string => 'B'.$state)->sortable(),
                TextColumn::make('source_revision')->label('Revision')->searchable(),
                TextColumn::make('status')->state(fn (BidPackage $record): string => $record->archived_at !== null ? 'Archived' : ($record->published_at !== null ? 'Published' : 'Draft'))->badge(),
                TextColumn::make('importer.email')->label('Imported by')->placeholder('Deleted user')->toggleable(),
                TextColumn::make('line_count')->label('Lines')->state(fn (BidPackage $record): int => count($record->payload['lines'] ?? [])),
                TextColumn::make('trip_count')->label('Trips')->state(fn (BidPackage $record): int => count($record->payload['trips'] ?? [])),
                TextColumn::make('workspaces_count')->label('Workspaces')->counts('workspaces'),
                TextColumn::make('published_at')->label('Published (UTC)')->dateTime(timezone: 'UTC')->placeholder('Unpublished')->sortable(),
            ])
            ->filters([
                SelectFilter::make('fleet')->options(['747' => 'B747', '777' => 'B777']),
                SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value']) {
                            'draft' => $query->whereNull('published_at'),
                            'published' => $query->whereNotNull('published_at')->whereNull('archived_at'),
                            'archived' => $query->whereNotNull('archived_at'),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
