<?php

namespace App\View\Models;

use App\DTOs\CrewBidBrowseCriteria;
use App\DTOs\CrewBidsPageData;
use App\Models\BidPackage;
use App\Models\CrewBidPayProfile;
use App\Models\User;
use App\Services\CrewBids\BrowseBidLines;
use App\Services\CrewBids\CompareBidPackageRevision;
use Carbon\CarbonImmutable;

class CrewBidsPageDataFactory
{
    public function __construct(
        private BrowseBidLines $browseBidLines,
        private CompareBidPackageRevision $compareRevision,
    ) {}

    public function make(?BidPackage $package, User $user, CrewBidBrowseCriteria $criteria, ?int $draftId = null, ?int $pendingPackageId = null): CrewBidsPageData
    {
        $profile = CrewBidPayProfile::query()->where('user_id', $user->id)->first() ?? new CrewBidPayProfile;
        $context = $package === null ? [] : $profile->context($package->id, $criteria->seat, $user);
        $browse = $this->browseBidLines->browse($package, $criteria, $context);
        $month = $package === null ? null : CarbonImmutable::parse($package->period.'-01', 'UTC');
        $target = $pendingPackageId === null ? null : BidPackage::query()->whereNotNull('published_at')->find($pendingPackageId);
        $ranks = array_flip($criteria->selections);
        $showBlocks = in_array('blocks', $criteria->preferences['goals'], true);
        $filterSummaries = $this->filterSummaries($criteria);
        $activeFilters = array_merge(...array_values($filterSummaries));
        if ($criteria->selectedOnly) {
            $activeFilters[] = 'Selected only';
        }

        return new CrewBidsPageData(
            package: $package,
            packages: BidPackage::query()->whereNotNull('published_at')
                ->where(fn ($query) => $query->whereNull('archived_at')->orWhereHas('workspaces', fn ($query) => $query->where('user_id', $user->id)->where('seat', $criteria->seat)))
                ->orderByDesc('period')->orderByDesc('id')->get(['id', 'period', 'fleet', 'source_revision']),
            browse: $browse,
            lineCards: array_map(static fn (array $line): CrewBidLineViewModel => CrewBidLineViewModel::fromLine($line, isset($ranks[$line['id']]) ? $ranks[$line['id']] + 1 : null, $showBlocks), $browse->lines),
            filterSummaries: $filterSummaries,
            activeFilterCount: count($activeFilters),
            activeFilterSummary: $activeFilters === [] ? 'No active browsing filters' : implode(' · ', $activeFilters),
            dates: $this->dates($month),
            monthLabel: $month?->format('F Y'),
            packageStartDate: $month?->format('Y-m-d'),
            packageEndDate: $month?->endOfMonth()->format('Y-m-d'),
            trips: $package->payload['trips'] ?? [],
            draft: $draftId === null ? null : BidPackage::query()->where('imported_by', $user->id)->find($draftId),
            target: $target,
            revisionChanges: $this->compareRevision->compare($package, $target, $criteria->seat, $criteria->selections, $criteria->preferences),
            latest: $package === null ? null : BidPackage::query()->whereNotNull('published_at')->whereNull('archived_at')
                ->where('period', $package->period)->where('fleet', $package->fleet)->where('id', '>', $package->id)->latest('id')->first(),
            copyText: implode("\n", array_map(fn (string $id, int $index): string => ($index + 1).'. '.$id, $criteria->selections, array_keys($criteria->selections))),
            goalLabels: ['credit' => 'Maximize credit', 'days_off' => 'Maximize days off', 'blocks' => 'Fewer work blocks'],
        );
    }

    /** @return list<array{date: string, day: int, weekday: string}> */
    private function dates(?CarbonImmutable $month): array
    {
        $dates = [];
        for ($day = 1; $month !== null && $day <= $month->daysInMonth; $day++) {
            $date = $month->addDays($day - 1);
            $dates[] = ['date' => $date->format('Y-m-d'), 'day' => $day, 'weekday' => $date->format('D')];
        }

        return $dates;
    }

    /** @return array{credit: list<string>, work_pattern: list<string>, airports: list<string>} */
    private function filterSummaries(CrewBidBrowseCriteria $criteria): array
    {
        $airports = [];
        if ($criteria->layoverCity !== '') {
            $airports[] = 'Layover '.$criteria->layoverCity;
        }
        if ($criteria->extendedLayoversOnly) {
            $airports[] = '24h+ layover';
        }
        if ($criteria->avoidShortLayovers) {
            $airports[] = 'Avoid under 10h';
        }

        return [
            'credit' => $criteria->minimumCredit === null ? [] : ['Min credit '.$criteria->minimumCredit.'h'],
            'work_pattern' => $criteria->minimumDaysOff === null ? [] : ['Min days off '.$criteria->minimumDaysOff],
            'airports' => $airports,
        ];
    }
}
