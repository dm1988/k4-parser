<?php

namespace App\Filament\Resources\CrewBidSnapshots\Pages;

use App\Filament\Resources\CrewBidSnapshots\CrewBidSnapshotResource;
use App\Models\BidPackage;
use App\Models\CrewBidSnapshot;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/** @extends ViewRecord<CrewBidSnapshot> */
class ViewCrewBidSnapshot extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CrewBidSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        $snapshot = $this->getRecord();
        $package = BidPackage::query()->where('period', $snapshot->period)->where('fleet', $snapshot->fleet)
            ->whereNotNull('published_at')->whereNull('archived_at')->orderByDesc('id')->first();

        return [
            Action::make('refreshSharedBids')->label('Refresh from Kalitta')
                ->authorize(fn (): bool => Gate::allows('manage-crew-bids'))
                ->url($package === null ? null : route('crew-bids.employees.index', ['package' => $package, 'seat' => $snapshot->seat]))
                ->disabled($package === null)
                ->tooltip($package === null ? 'Publish a matching month/fleet package first.' : 'Open the protected refresh page and enter operation-local portal credentials.'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('infolist'), EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table->heading('Shared submitted choices')->searchable()->paginationPageOptions([25, 50, 100])
            ->records(function (?string $search, int $page, int $recordsPerPage): LengthAwarePaginator {
                Gate::authorize('view', $this->getRecord());
                $bidders = collect($this->getRecord()->bidders)
                    ->map(fn (array $bidder, int|string $id): array => [
                        'employee' => (string) $id, 'name' => $bidder['name'], 'seniority' => $bidder['seniority'],
                        'choice_count' => count($bidder['numbers']), 'choices' => implode(', ', $bidder['numbers']),
                    ])
                    ->filter(fn (array $bidder): bool => $search === null || mb_stripos($bidder['employee'].' '.$bidder['name'], $search) !== false)
                    ->sortBy('seniority');

                return new LengthAwarePaginator($bidders->forPage($page, $recordsPerPage), $bidders->count(), $recordsPerPage, $page);
            })
            ->columns([
                TextColumn::make('employee')->label('Employee number'),
                TextColumn::make('name'),
                TextColumn::make('seniority')->label('Portal seniority'),
                TextColumn::make('choice_count')->label('Choices'),
                TextColumn::make('choices')->label('Submitted lines in order')->placeholder('No submitted choices')->wrap(),
            ]);
    }
}
