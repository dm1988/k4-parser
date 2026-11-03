<?php

namespace App\Services\CrewBids;

use App\DTOs\CrewBidBrowseCriteria;
use App\DTOs\CrewBidBrowseResult;
use App\Models\BidPackage;

class BrowseBidLines
{
    public function __construct(private BidLineMatcher $matcher, private CalculateCrewBidPay $pay) {}

    /** @param array<string, mixed> $payContext */
    public function browse(?BidPackage $package, CrewBidBrowseCriteria $criteria, array $payContext = []): CrewBidBrowseResult
    {
        $payload = $package === null ? [] : $package->payload;
        $paySource = $package === null ? null : $package->pay_source;
        $payPayload = ($paySource['package_fingerprint'] ?? null) === $package?->fingerprint
            ? ($paySource['payload'] ?? $payload) : $payload;
        $allLines = $this->eligibleLines($package, $criteria, $payload);
        foreach ($allLines as &$line) {
            $line['pay_estimate'] = $this->pay->calculate($payPayload['lines'][$line['id']] ?? $line, $payPayload['trips'] ?? [], $package->period, $package->fleet, $criteria->seat, $payContext);
            $line['pay_compact'] = true;
        }
        unset($line);
        $lines = array_values(array_filter($allLines, fn (array $line): bool => $this->matches($line, $criteria)));
        if ($criteria->sort === 'pay') {
            usort($lines, static function (array $left, array $right): int {
                $a = $left['pay_estimate']->cents;
                $b = $right['pay_estimate']->cents;

                return ($a === null ? 1 : 0) <=> ($b === null ? 1 : 0)
                    ?: ($b ?? 0) <=> ($a ?? 0) ?: strnatcmp($left['id'], $right['id']);
            });
        } else {
            $lines = $this->matcher->sort($lines, $criteria->preferences['goals'], $criteria->sort);
        }
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

    /** @param array<string, mixed> $payload
     * @return array<string, array<string, mixed>>
     */
    private function eligibleLines(?BidPackage $package, CrewBidBrowseCriteria $criteria, array $payload): array
    {
        if ($package === null) {
            return [];
        }

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
