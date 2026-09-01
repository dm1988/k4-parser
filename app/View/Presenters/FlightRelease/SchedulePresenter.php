<?php

namespace App\View\Presenters\FlightRelease;

use App\DTOs\SlotTimeData;
use App\View\Models\FlightPlanPageData;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class SchedulePresenter
{
    public function __construct(private ?FlightPlanPageData $pageData) {}

    public function etdUtc(): ?string
    {
        return $this->pageData?->flightPlan->schedule->etdUtc;
    }

    public function etaUtc(): ?string
    {
        return $this->pageData?->flightPlan->schedule->etaUtc;
    }

    public function overviewEtdUtc(): ?string
    {
        return $this->formatUtcPart($this->etdUtc(), 'M j, Y · Hi\Z');
    }

    public function overviewEtaUtc(): ?string
    {
        return $this->formatUtcPart($this->etaUtc(), 'M j, Y · Hi\Z');
    }

    public function departureDate(?string $fallbackDate): ?string
    {
        return $this->formatUtcPart($this->etdUtc(), 'M j, Y') ?? $fallbackDate;
    }

    public function departureTime(): ?string
    {
        return $this->formatUtcPart($this->etdUtc(), 'Hi');
    }

    public function arrivalDate(): ?string
    {
        return $this->formatUtcPart($this->etaUtc(), 'M j, Y');
    }

    public function arrivalTime(): ?string
    {
        return $this->formatUtcPart($this->etaUtc(), 'Hi');
    }

    public function overviewSlotSummary(): ?string
    {
        $slotCount = count($this->pageData?->flightPlan->schedule->slots ?? []);

        return $slotCount === 0
            ? null
            : $slotCount.' approved UTC '.($slotCount === 1 ? 'slot' : 'slots');
    }

    /** @return list<array{direction: string, airport: string, date: string, time: string, sourceTime: string, timeBasis: string, tolerance: ?string, window: ?string, plannedArrival: ?string, comparison: ?string, plannedPosition: ?float}> */
    public function slotTimes(): array
    {
        return array_map(
            fn (SlotTimeData $slot): array => $this->slotTime($slot),
            $this->pageData?->flightPlan->schedule->slots ?? [],
        );
    }

    public function slotSourceText(): ?string
    {
        return $this->pageData?->flightPlan->schedule->slotSourceText;
    }

    /** @return array{direction: string, airport: string, date: string, time: string, sourceTime: string, timeBasis: string, tolerance: ?string, window: ?string, plannedArrival: ?string, comparison: ?string, plannedPosition: ?float} */
    private function slotTime(SlotTimeData $slot): array
    {
        $tolerance = $slot->toleranceMinutes;
        $plannedArrival = null;
        $comparison = null;
        $plannedPosition = null;

        if ($slot->direction->value === 'arrival' && $tolerance !== null && $tolerance > 0 && $this->etaUtc() !== null) {
            try {
                $eta = CarbonImmutable::parse($this->etaUtc())->utc();
                $offsetMinutes = $slot->instantUtc->diffInMinutes($eta, false);
                $plannedArrival = $eta->format('M j, Hi\Z').' UTC';
                $comparison = abs($offsetMinutes) <= $tolerance
                    ? 'Planned ETA is within the confirmed window'
                    : 'Planned ETA is outside the confirmed window';
                $plannedPosition = max(0, min(100, 50 + (($offsetMinutes / ($tolerance * 4)) * 100)));
            } catch (Throwable) {
            }
        }

        return [
            'direction' => $slot->direction->label(),
            'airport' => $slot->airport->value,
            'date' => $slot->instantUtc->format('M j, Y'),
            'time' => $slot->instantUtc->format('Hi').'Z',
            'sourceTime' => $slot->sourceTime,
            'timeBasis' => 'UTC',
            'tolerance' => $tolerance === null ? null : '± '.$tolerance.' min',
            'window' => $tolerance === null ? null : sprintf(
                '%s–%s UTC',
                $slot->instantUtc->subMinutes($tolerance)->format('M j, Hi\Z'),
                $slot->instantUtc->addMinutes($tolerance)->format('M j, Hi\Z'),
            ),
            'plannedArrival' => $plannedArrival,
            'comparison' => $comparison,
            'plannedPosition' => $plannedPosition,
        ];
    }

    private function formatUtcPart(?string $value, string $format): ?string
    {
        if ($value === null || preg_match('/(?:Z|\+00:00)\z/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc()->format($format);
        } catch (Throwable) {
            return null;
        }
    }
}
