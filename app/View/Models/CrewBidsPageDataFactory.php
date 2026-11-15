<?php

namespace App\View\Models;

use App\DTOs\CrewBidBrowseCriteria;
use App\DTOs\CrewBidsPageData;
use App\Enums\CrewBidGoal;
use App\Enums\CrewBidSort;
use App\Models\BidPackage;
use App\Models\CrewBidPayProfile;
use App\Models\User;
use App\Services\CrewBids\BrowseBidLines;
use App\Services\CrewBids\CompareBidPackageRevision;
use App\Services\CrewBids\ResolveSubmittedBidOrder;
use Carbon\CarbonImmutable;

class CrewBidsPageDataFactory
{
    public function __construct(
        private BrowseBidLines $browseBidLines,
        private CompareBidPackageRevision $compareRevision,
        private ResolveSubmittedBidOrder $submittedOrder,
    ) {}

    /** @param list<string> $payDetails */
    public function make(?BidPackage $package, User $user, CrewBidBrowseCriteria $criteria, ?int $draftId = null, ?int $pendingPackageId = null, array $payDetails = []): CrewBidsPageData
    {
        $submittedOrder = $criteria->submittedOrder ?? $this->submittedOrder->resolve($user, $package, $criteria->seat);
        $criteria = $criteria->withSubmittedOrder($submittedOrder);
        $profile = CrewBidPayProfile::query()->where('user_id', $user->id)->first() ?? new CrewBidPayProfile;
        $context = $package === null ? [] : $profile->context($package->id, $criteria->seat, $user);
        $browse = $this->browseBidLines->browse($package, $criteria, $context);
        $month = $package === null ? null : CarbonImmutable::parse($package->period.'-01', 'UTC');
        $target = $pendingPackageId === null ? null : BidPackage::query()->whereNotNull('published_at')->find($pendingPackageId);
        $ranks = array_flip($criteria->selections);
        $goals = $criteria->rankingGoals();
        $showBlocks = in_array(CrewBidGoal::Blocks, $goals, true);
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
            lineCards: array_map(static fn (array $line): CrewBidLineViewModel => CrewBidLineViewModel::fromLine(
                line: $line,
                rank: isset($ranks[$line['id']]) ? $ranks[$line['id']] + 1 : null,
                showBlocks: $showBlocks,
                payDetails: in_array($line['id'], $payDetails, true),
                goals: $goals,
                submittedRank: $criteria->sort === CrewBidSort::Submitted && $submittedOrder->available() ? ($submittedOrder->ranks[$line['id']] ?? null) : null,
            ), $browse->lines),
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
            aiPrompt: $this->aiPrompt($criteria->selections, $browse->allLines),
            goalLabels: CrewBidGoal::options(),
            submittedOrder: $submittedOrder,
            submissionStatus: CrewBidSubmissionStatusViewModel::fromOrder($submittedOrder, $criteria->selections),
        );
    }

    /**
     * @param  list<string>  $selections
     * @param  array<string, array<string, mixed>>  $availableLines
     */
    private function aiPrompt(array $selections, array $availableLines): ?string
    {
        if ($selections === []) {
            return null;
        }

        $values = [];
        $rows = [];
        foreach ($selections as $index => $id) {
            if (! isset($availableLines[$id]) || preg_match('/\A[FQR]([1-9]\d{0,3})\z/', $id, $match) !== 1 || isset($values[(int) $match[1]])) {
                return null;
            }

            $values[(int) $match[1]] = true;
            $rows[] = ($index + 1).' '.$match[1];
        }

        return 'Fill in the form using the following list where the leading number is the **Rank** (the form field number) and the second number is the **Value** to be entered into that field. The input fields follow the ID pattern `ContentPlaceHolder1_tb_l{Rank}`.'."\n\n".implode("\n", $rows);
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
