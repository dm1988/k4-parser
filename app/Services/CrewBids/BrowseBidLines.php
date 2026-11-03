<?php

namespace App\Services\CrewBids;

use App\DTOs\CrewBidBrowseCriteria;
use App\DTOs\CrewBidBrowseResult;
use App\Models\BidPackage;

class BrowseBidLines
{
    public function __construct(private BidLineMatcher $matcher) {}

    public function browse(?BidPackage $package, CrewBidBrowseCriteria $criteria): CrewBidBrowseResult
    {
        $allLines = $this->eligibleLines($package, $criteria);
        $lines = array_values(array_filter($allLines, fn (array $line): bool => $this->matches($line, $criteria)));
        $lines = $this->matcher->sort($lines, $criteria->preferences['goals'], $criteria->sort);
        $selected = [];
        foreach ($criteria->selections as $id) {
            $selected[] = $allLines[$id] ?? [
                'id' => $id,
                'credit_label' => 'Not available',
                'block_label' => 'Not available',
                'days_off' => null,
                'warnings' => ['Unavailable in this revision'],
            ];
        }

        $cities = array_values(array_unique(array_filter(array_column($allLines, 'start_city'))));
        sort($cities);
        $layoverCities = array_values(array_unique(array_merge(...array_column($allLines, 'layover_airports'))));
        sort($layoverCities);

        return new CrewBidBrowseResult(
            allLines: $allLines,
            lines: $lines,
            selected: $selected,
            cities: $cities,
            layoverCities: $layoverCities,
            total: count(array_filter($allLines, fn (array $line): bool => $line['type'] === $criteria->tab)),
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function eligibleLines(?BidPackage $package, CrewBidBrowseCriteria $criteria): array
    {
        if ($package === null) {
            return [];
        }

        $payload = $package->payload;
        $seatKey = $criteria->seat === 'CA' ? 'captains' : 'first_officers';
        $layoverFilters = $criteria->layoverCity !== '' || $criteria->extendedLayoversOnly || $criteria->avoidShortLayovers
            ? ['city' => $criteria->layoverCity, 'extended' => $criteria->extendedLayoversOnly, 'avoid_short' => $criteria->avoidShortLayovers]
            : [];
        $lines = [];
        foreach ($payload['lines'] ?? [] as $line) {
            if ($line[$seatKey] > 0) {
                $lines[$line['id']] = $this->matcher->present($line, $package->period, $criteria->preferences, $payload['trips'] ?? [], $layoverFilters);
            }
        }

        return $lines;
    }

    /** @param array<string, mixed> $line */
    private function matches(array $line, CrewBidBrowseCriteria $criteria): bool
    {
        return $line['type'] === $criteria->tab
            && $line['matches']
            && $line['layover_matches']
            && (! $criteria->selectedOnly || in_array($line['id'], $criteria->selections, true))
            && ($criteria->minimumCredit === null || ($line['credit'] !== null && ($line['credit_seconds'] ?? $line['credit'] * 60) >= $criteria->minimumCredit * 3600))
            && ($criteria->minimumDaysOff === null || ($line['days_off'] !== null && $line['days_off'] >= $criteria->minimumDaysOff));
    }
}
