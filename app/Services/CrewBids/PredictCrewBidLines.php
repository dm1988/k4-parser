<?php

namespace App\Services\CrewBids;

use App\Models\BidPackage;
use App\Models\CrewBidSnapshot;
use App\Models\PilotSeniorityList;
use App\Models\User;

/**
 * @phpstan-type Bidder array{id: string, name: string, seniority: int, rank: int}
 * @phpstan-type LinePrediction array{status: string, label: string, capacity: ?int, remaining: ?int, bidders: list<Bidder>, occupants: list<Bidder>}
 * @phpstan-type Prediction array{available: bool, reason: string, stale: bool, checked_at: ?string, bidder: ?string, likely: ?string, basis: string, employees: array<string, array{name: string, seniority: int}>, lines: array<string, LinePrediction>}
 */
class PredictCrewBidLines
{
    /**
     * @param  list<string>  $selections
     * @return array{available: bool, reason: string, stale: bool, checked_at: ?string, bidder: ?string, likely: ?string, basis: string, employees: array<string, array{name: string, seniority: int}>, lines: array<string, LinePrediction>, seniority_date: ?string, employee_name: ?string, employee_seniority: ?int}
     */
    public function forUser(?BidPackage $package, ?CrewBidSnapshot $snapshot, string $seat, User $user, array $selections): array
    {
        $list = PilotSeniorityList::current();
        $employee = $user->crew_bid_employee_verified_at === null ? '' : ($user->crew_bid_employee_number ?? '');
        $pilot = $list->pilots[$employee] ?? null;
        $reason = null;
        if ($list === null) {
            $reason = 'Ask an admin to import the pilot seniority list to enable personal predictions.';
        } elseif ($employee === '') {
            $reason = 'Link your employee number once to enable your personal predictions.';
        } elseif ($pilot === null) {
            $reason = 'Your linked employee number is absent from the current seniority list. Ask an admin to update it.';
        }
        $working = $snapshot === null ? null : clone $snapshot;
        if ($reason === null && $working !== null) {
            $bidders = $working->bidders;
            foreach ($bidders as $id => &$bidder) {
                $entry = $list->pilots[$id] ?? null;
                if ($entry === null) {
                    $reason = 'Predictions unavailable: the seniority list is missing a shared bidder. Ask an admin to update it.';
                    break;
                }
                $bidder['seniority'] = $entry['seniority'];
            }
            unset($bidder);
            if (! isset($bidders[$employee])) {
                $bidders[$employee] = $pilot + ['numbers' => []];
            }
            $working->bidders = $bidders;
        }
        $result = $this->predict($package, $reason === null ? $working : null, $seat, $employee, $selections);
        if ($reason !== null) {
            $result['reason'] = $reason;
        }
        if ($snapshot !== null) {
            $result['checked_at'] = $snapshot->retrieved_at->utc()->format('Y-m-d H:i:s').' UTC';
            $result['stale'] = $snapshot->stale();
        }

        return $result + ['seniority_date' => $list?->effective_date->format('Y-m-d'), 'employee_name' => $pilot['name'] ?? null, 'employee_seniority' => $pilot['seniority'] ?? null];
    }

    /**
     * @param  list<string>  $selections
     * @return Prediction
     */
    public function predict(?BidPackage $package, ?CrewBidSnapshot $snapshot, string $seat, string $employee, array $selections): array
    {
        $result = ['available' => false, 'reason' => 'Refresh shared bids to enable predictions.', 'stale' => false, 'checked_at' => null,
            'bidder' => null, 'likely' => null, 'basis' => 'Your current bid list', 'employees' => [], 'lines' => []];
        if ($package === null || $snapshot === null || $snapshot->period !== $package->period || $snapshot->fleet !== $package->fleet || $snapshot->seat !== $seat) {
            return $result;
        }
        $result['checked_at'] = $snapshot->retrieved_at->utc()->format('Y-m-d H:i:s').' UTC';
        $result['stale'] = $snapshot->stale();
        $bidders = $snapshot->bidders;
        $seniority = [];
        foreach ($bidders as $id => $bidder) {
            if (! is_int($bidder['seniority']) || $bidder['seniority'] < 1 || isset($seniority[$bidder['seniority']])) {
                $result['reason'] = 'Predictions unavailable: seniority is missing or ambiguous.';

                return $result;
            }
            $seniority[$bidder['seniority']] = true;
            $result['employees'][$id] = ['name' => $bidder['name'], 'seniority' => $bidder['seniority']];
        }
        uasort($bidders, static fn (array $left, array $right): int => $left['seniority'] <=> $right['seniority']);
        uasort($result['employees'], static fn (array $left, array $right): int => $left['seniority'] <=> $right['seniority']);
        $index = [];
        $field = $seat === 'CA' ? 'captains' : 'first_officers';
        foreach ($package->payload['lines'] ?? [] as $id => $line) {
            if (preg_match('/\A[FQR]\s*([1-9]\d{0,3})\z/', $id, $match)) {
                $index[(int) $match[1]][] = $id;
            }
            $capacity = $line[$field] ?? null;
            $capacity = is_int($capacity) && $capacity >= 0 ? $capacity : null;
            $result['lines'][$id] = ['status' => 'unknown', 'label' => 'Prediction unavailable', 'capacity' => $capacity, 'remaining' => null, 'bidders' => [], 'occupants' => []];
        }
        $numbers = [];
        foreach ($index as $number => $ids) {
            if (count($ids) === 1) {
                $numbers[$number] = $ids[0];
            }
        }
        foreach ($bidders as $id => $bidder) {
            $listed = [];
            foreach ($bidder['numbers'] as $rank => $number) {
                if (isset($numbers[$number]) && ! isset($listed[$number])) {
                    $listed[$number] = true;
                    $result['lines'][$numbers[$number]]['bidders'][] = ['id' => (string) $id, 'name' => $bidder['name'], 'seniority' => $bidder['seniority'], 'rank' => $bidder['ranks'][$rank] ?? $rank + 1];
                }
            }
        }
        if (! isset($bidders[$employee])) {
            $result['reason'] = 'Your employee number was not found in the shared bid snapshot.';

            return $result;
        }
        $self = $bidders[$employee];
        $result['bidder'] = $self['name'];
        $incomplete = false;
        foreach ($bidders as $id => $bidder) {
            if ($bidder['seniority'] >= $self['seniority']) {
                break;
            }
            foreach ($bidder['numbers'] as $rank => $number) {
                $lineId = $numbers[$number] ?? null;
                if ($lineId === null || $result['lines'][$lineId]['capacity'] === null || $result['lines'][$lineId]['capacity'] === 0) {
                    $incomplete = true;
                    break 2;
                }
                $line = $result['lines'][$lineId];
                if (count($line['occupants']) < $line['capacity']) {
                    $result['lines'][$lineId]['occupants'][] = ['id' => (string) $id, 'name' => $bidder['name'], 'seniority' => $bidder['seniority'], 'rank' => $bidder['ranks'][$rank] ?? $rank + 1];
                    break;
                }
            }
        }
        if ($incomplete) {
            $result['reason'] = 'Predictions unavailable: a senior choice has an unknown or ambiguous line/capacity.';
            foreach ($result['lines'] as &$line) {
                $line['occupants'] = [];
                $line['label'] = 'Prediction unavailable';
            }
            unset($line);

            return $result;
        }
        $result['available'] = true;
        $result['reason'] = 'Seniors receive their highest available choice first. Later bids and eligibility rules may change the result.';
        foreach ($result['lines'] as $id => &$line) {
            $unique = preg_match('/\A[FQR]\s*([1-9]\d{0,3})\z/', $id, $match) === 1 && isset($numbers[(int) $match[1]]);
            if ($line['capacity'] === null || ! $unique) {
                $line['label'] = 'Prediction unavailable';

                continue;
            }
            $line['remaining'] = max(0, $line['capacity'] - count($line['occupants']));
            $line['status'] = $line['capacity'] === 0 ? 'ineligible' : ($line['remaining'] === 0 ? 'unavailable' : 'available');
            $line['label'] = match ($line['status']) {
                'ineligible' => 'No '.$seat.' positions',
                'unavailable' => 'Projected unavailable',
                default => 'Projected available',
            };
        }
        unset($line);
        $ordered = $selections;
        if ($ordered === []) {
            $result['basis'] = 'Your submitted order';
            foreach ($self['numbers'] as $number) {
                $ordered[] = $numbers[$number] ?? '';
            }
        }
        foreach ($ordered as $id) {
            $line = $result['lines'][$id] ?? null;
            if ($line === null || $line['status'] === 'unknown' || $line['status'] === 'ineligible') {
                $result['reason'] = 'Likely hold unavailable: an earlier choice has an unknown line/capacity.';
                break;
            }
            if ($line['status'] === 'available') {
                $result['likely'] = $id;
                $result['lines'][$id]['status'] = 'likely';
                $result['lines'][$id]['label'] = 'Likely hold';
                break;
            }
        }

        return $result;
    }
}
