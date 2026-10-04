<?php

namespace App\View\Presenters\FlightRelease;

use App\DTOs\SlotTimeData;
use App\Enums\SlotDirection;
use App\View\Models\FlightPlanPageData;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Number;

final readonly class SchedulePresenter
{
    private const int CLOSE_WINDOW_MINUTES = 10;

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
        $slotCount = $this->overviewSlotCount();

        return $slotCount === null
            ? null
            : $slotCount.' '.$this->overviewSlotLabel();
    }

    public function overviewSlotCount(): ?int
    {
        $count = count($this->pageData?->flightPlan->schedule->slots ?? []);

        return $count === 0 ? null : $count;
    }

    public function overviewSlotLabel(): string
    {
        return $this->overviewSlotCount() === 1 ? 'approved slot time' : 'approved slot times';
    }

    /** @return list<string> */
    public function overviewSlotAlerts(): array
    {
        return array_values(array_unique(array_filter(array_column($this->slotTimes(), 'alert'))));
    }

    public function overviewSlotCardClasses(): ?string
    {
        return $this->overviewSlotAlerts() === []
            ? null
            : 'border-amber-500/30 border-l-4 border-l-amber-500 bg-amber-500/5 backdrop-blur dark:border-amber-400/30 dark:border-l-amber-400 dark:bg-amber-400/10';
    }

    /** @return list<array{direction: string, airport: string, date: string, time: string, sourceTime: string, timeBasis: string, tolerance: ?string, window: ?string, comparisonHeading: ?string, plannedTime: ?string, comparison: ?string, plannedPosition: ?float, buffer: ?string, bufferBasis: string, alert: ?string, alertDetail: ?string}> */
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

    /** @return array{direction: string, airport: string, date: string, time: string, sourceTime: string, timeBasis: string, tolerance: ?string, window: ?string, comparisonHeading: ?string, plannedTime: ?string, comparison: ?string, plannedPosition: ?float, buffer: ?string, bufferBasis: string, alert: ?string, alertDetail: ?string} */
    private function slotTime(SlotTimeData $slot): array
    {
        $tolerance = $slot->toleranceMinutes;
        $plannedValue = match ($slot->direction) {
            SlotDirection::Departure => $this->etdUtc(),
            SlotDirection::Arrival => $this->etaUtc(),
            default => null,
        };
        $comparisonHeading = $slot->direction->comparisonHeading();
        $plannedTimeLabel = $slot->direction->plannedTimeLabel();
        $bufferBasis = $plannedTimeLabel === null
            ? 'A confirmed slot direction is required to calculate the buffer.'
            : 'Planned '.$plannedTimeLabel.' minus the earliest '.$slot->direction->value.' window time (UTC). Negative values are before the window.';
        $comparison = $this->slotComparison($slot, $this->parseUtc($plannedValue), $plannedTimeLabel);

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
            'comparisonHeading' => $comparisonHeading,
            'plannedTime' => $comparison['plannedTime'],
            'comparison' => $comparison['comparison'],
            'plannedPosition' => $comparison['plannedPosition'],
            'buffer' => $comparison['buffer'],
            'bufferBasis' => $bufferBasis,
            'alert' => $comparison['alert'],
            'alertDetail' => $comparison['alertDetail'],
        ];
    }

    /** @return array{plannedTime: ?string, comparison: ?string, plannedPosition: ?float, buffer: ?string, alert: ?string, alertDetail: ?string} */
    private function slotComparison(SlotTimeData $slot, ?CarbonImmutable $plannedInstant, ?string $plannedTimeLabel): array
    {
        $tolerance = $slot->toleranceMinutes;

        if ($plannedInstant === null || $plannedTimeLabel === null || $tolerance === null || $tolerance < 0) {
            return ['plannedTime' => null, 'comparison' => null, 'plannedPosition' => null, 'buffer' => null, 'alert' => null, 'alertDetail' => null];
        }

        $offsetMinutes = $slot->instantUtc->diffInMinutes($plannedInstant, false);
        $windowStart = $slot->instantUtc->subMinutes($tolerance);
        $windowEnd = $slot->instantUtc->addMinutes($tolerance);
        $minutesFromStart = $windowStart->diffInMinutes($plannedInstant, false);
        $minutesUntilEnd = $plannedInstant->diffInMinutes($windowEnd, false);
        $alert = $this->slotAlert($slot, $plannedTimeLabel, $plannedInstant->equalTo($windowStart), $minutesFromStart, $minutesUntilEnd);

        return [
            'plannedTime' => $plannedInstant->format('M j, Hi\Z').' UTC',
            'comparison' => abs($offsetMinutes) <= $tolerance
                ? 'Planned '.$plannedTimeLabel.' is within the confirmed window'
                : 'Planned '.$plannedTimeLabel.' is outside the confirmed window',
            'plannedPosition' => $tolerance === 0
                ? ($offsetMinutes === 0.0 ? 50.0 : ($offsetMinutes < 0 ? 0.0 : 100.0))
                : max(0, min(100, 50 + (($offsetMinutes / ($tolerance * 4)) * 100))),
            'buffer' => Number::format($minutesFromStart, maxPrecision: 2, locale: 'en').' min',
            ...$alert,
        ];
    }

    /** @return array{alert: ?string, alertDetail: ?string} */
    private function slotAlert(SlotTimeData $slot, string $plannedTimeLabel, bool $atWindowStart, float $minutesFromStart, float $minutesUntilEnd): array
    {
        if ($atWindowStart) {
            return [
                'alert' => 'Do not depart early',
                'alertDetail' => 'Planned '.$plannedTimeLabel.' equals the earliest approved '.$slot->direction->value.' time (UTC).',
            ];
        }

        if ($minutesFromStart < 0 || $minutesUntilEnd < 0) {
            return [
                'alert' => 'Planned time outside slot window',
                'alertDetail' => 'Planned '.$plannedTimeLabel.' is outside the confirmed UTC window. Review the approved slot.',
            ];
        }

        if (min($minutesFromStart, $minutesUntilEnd) <= self::CLOSE_WINDOW_MINUTES) {
            return [
                'alert' => 'Close UTC slot window',
                'alertDetail' => 'Planned '.$plannedTimeLabel.' is within '.self::CLOSE_WINDOW_MINUTES.' min of a confirmed window boundary.',
            ];
        }

        return ['alert' => null, 'alertDetail' => null];
    }

    private function formatUtcPart(?string $value, string $format): ?string
    {
        return $this->parseUtc($value)?->format($format);
    }

    private function parseUtc(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/(?:Z|\+00:00)\z/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
