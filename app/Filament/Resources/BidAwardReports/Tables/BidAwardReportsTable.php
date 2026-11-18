<?php

namespace App\Filament\Resources\BidAwardReports\Tables;

use App\Models\BidAwardReport;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BidAwardReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('package'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('Report #')->sortable(),
                TextColumn::make('period')->label('Month')->searchable()->sortable(),
                TextColumn::make('fleet')->formatStateUsing(fn (string $state): string => 'B'.$state)->sortable(),
                TextColumn::make('package.source_revision')->label('Schedule revision'),
                TextColumn::make('bid_package_id')->label('Package #'),
                TextColumn::make('award_count')->label('Employees')->state(fn (BidAwardReport $record): int => count($record->awards)),
                TextColumn::make('created_at')->label('Imported (UTC)')->dateTime(timezone: 'UTC')->sortable(),
            ])
            ->filters([
                SelectFilter::make('fleet')->options(['747' => 'B747', '777' => 'B777']),
            ])
            ->recordActions([
                DeleteAction::make()->label('Remove report')->modalDescription('Remove this awards report from the workspace selector and disable its downloads. Bid packages and personal bid lists remain available.'),
            ])
            ->emptyStateHeading('No awards uploaded')
            ->emptyStateDescription('Upload a text-based awards PDF and choose its published schedule revision.');
    }
}
