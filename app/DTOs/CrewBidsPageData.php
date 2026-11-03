<?php

namespace App\DTOs;

use App\Models\BidPackage;
use App\View\Models\CrewBidLineViewModel;
use Illuminate\Database\Eloquent\Collection;

final readonly class CrewBidsPageData
{
    /**
     * @param  Collection<int, BidPackage>  $packages
     * @param  list<CrewBidLineViewModel>  $lineCards
     * @param  array{credit: list<string>, work_pattern: list<string>, airports: list<string>}  $filterSummaries
     * @param  list<array{date: string, day: int, weekday: string}>  $dates
     * @param  array<string, array<string, mixed>>  $trips
     * @param  list<array{id: string, status: string, details: list<string>}>  $revisionChanges
     * @param  array{credit: string, days_off: string, blocks: string}  $goalLabels
     */
    public function __construct(
        public ?BidPackage $package,
        public Collection $packages,
        public CrewBidBrowseResult $browse,
        public array $lineCards,
        public array $filterSummaries,
        public int $activeFilterCount,
        public string $activeFilterSummary,
        public array $dates,
        public ?string $monthLabel,
        public ?string $packageStartDate,
        public ?string $packageEndDate,
        public array $trips,
        public ?BidPackage $draft,
        public ?BidPackage $target,
        public array $revisionChanges,
        public ?BidPackage $latest,
        public string $copyText,
        public array $goalLabels,
    ) {}

    /**
     * @return array{
     *     package: ?BidPackage,
     *     packages: Collection<int, BidPackage>,
     *     lines: list<array<string, mixed>>,
     *     lineCards: list<CrewBidLineViewModel>,
     *     allLines: array<string, array<string, mixed>>,
     *     selected: list<array<string, mixed>>,
     *     cities: list<string>,
     *     layoverCities: list<string>,
     *     filterSummaries: array{credit: list<string>, work_pattern: list<string>, airports: list<string>},
     *     activeFilterCount: int,
     *     activeFilterSummary: string,
     *     dates: list<array{date: string, day: int, weekday: string}>,
     *     monthLabel: ?string,
     *     packageStartDate: ?string,
     *     packageEndDate: ?string,
     *     trips: array<string, array<string, mixed>>,
     *     total: int,
     *     draft: ?BidPackage,
     *     target: ?BidPackage,
     *     revisionChanges: list<array{id: string, status: string, details: list<string>}>,
     *     latest: ?BidPackage,
     *     copyText: string,
     *     goalLabels: array{credit: string, days_off: string, blocks: string}
     * }
     */
    public function toViewData(): array
    {
        return [
            'package' => $this->package,
            'packages' => $this->packages,
            'lines' => $this->browse->lines,
            'lineCards' => $this->lineCards,
            'allLines' => $this->browse->allLines,
            'selected' => $this->browse->selected,
            'cities' => $this->browse->cities,
            'layoverCities' => $this->browse->layoverCities,
            'filterSummaries' => $this->filterSummaries,
            'activeFilterCount' => $this->activeFilterCount,
            'activeFilterSummary' => $this->activeFilterSummary,
            'dates' => $this->dates,
            'monthLabel' => $this->monthLabel,
            'packageStartDate' => $this->packageStartDate,
            'packageEndDate' => $this->packageEndDate,
            'trips' => $this->trips,
            'total' => $this->browse->total,
            'draft' => $this->draft,
            'target' => $this->target,
            'revisionChanges' => $this->revisionChanges,
            'latest' => $this->latest,
            'copyText' => $this->copyText,
            'goalLabels' => $this->goalLabels,
        ];
    }
}
