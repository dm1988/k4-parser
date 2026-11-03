<?php

namespace App\Services\CrewBids;

use App\Models\BidPackage;

class CompareBidPackageRevision
{
    public function __construct(private BidLineMatcher $matcher) {}

    /**
     * @param  list<string>  $selections
     * @param  array{goals: list<string>, days_off: list<string>, start_city: string, initialized: bool}  $preferences
     * @return list<array{id: string, status: string, details: list<string>}>
     */
    public function compare(?BidPackage $package, ?BidPackage $target, string $seat, array $selections, array $preferences): array
    {
        if ($package === null || $target === null) {
            return [];
        }

        $payload = $package->payload;
        $targetPayload = $target->payload;
        $seatKey = $seat === 'CA' ? 'captains' : 'first_officers';
        $changes = [];
        foreach ($selections as $id) {
            $before = $payload['lines'][$id] ?? null;
            $after = $targetPayload['lines'][$id] ?? null;
            $details = [];
            if ($before !== null && $after !== null && $before !== $after) {
                $old = $this->matcher->present($before, $package->period, $preferences);
                $new = $this->matcher->present($after, $target->period, $preferences);
                $details[] = 'Credit '.$old['credit_label'].' → '.$new['credit_label'].'; days off '.($old['days_off'] ?? 'unknown').' → '.($new['days_off'] ?? 'unknown');
                $changedDates = [];
                foreach ($old['month'] as $date => $entry) {
                    if ($entry !== ($new['month'][$date] ?? null)) {
                        $changedDates[] = $date;
                    }
                }
                if ($changedDates !== []) {
                    $details[] = 'Changed UTC schedule dates: '.implode(', ', $changedDates);
                }
                $details[] = 'Start city '.($old['start_city'] ?? 'unknown').' → '.($new['start_city'] ?? 'unknown');
            }
            $changes[] = [
                'id' => $id,
                'status' => $after === null || $after[$seatKey] === 0 ? 'Unavailable — will be omitted' : ($before === $after ? 'Unchanged' : 'Changed — review before carrying forward'),
                'details' => $details,
            ];
        }

        return $changes;
    }
}
