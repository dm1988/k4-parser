<?php

namespace App\Filament\Resources\PilotSeniorityLists\Pages;

use App\Filament\Resources\PilotSeniorityLists\PilotSeniorityListResource;
use App\Models\PilotSeniorityList;
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

/** @extends ViewRecord<PilotSeniorityList> */
class ViewPilotSeniorityList extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PilotSeniorityListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PilotSeniorityListResource::importAction(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('infolist'), EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table->heading('Imported pilots')->searchable()->paginationPageOptions([25, 50, 100])
            ->records(function (?string $search, int $page, int $recordsPerPage): LengthAwarePaginator {
                Gate::authorize('view', $this->getRecord());
                $pilots = collect($this->getRecord()->pilots)
                    ->map(fn (array $pilot, int|string $id): array => ['employee' => (string) $id] + $pilot)
                    ->filter(fn (array $pilot): bool => $search === null || mb_stripos($pilot['employee'].' '.$pilot['name'].' '.$pilot['seniority'], $search) !== false)
                    ->sortBy('seniority');

                return new LengthAwarePaginator($pilots->forPage($page, $recordsPerPage), $pilots->count(), $recordsPerPage, $page);
            })
            ->columns([
                TextColumn::make('seniority'),
                TextColumn::make('employee')->label('Employee number'),
                TextColumn::make('name'),
            ]);
    }
}
