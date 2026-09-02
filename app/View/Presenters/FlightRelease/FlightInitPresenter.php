<?php

namespace App\View\Presenters\FlightRelease;

use App\View\Models\FlightPlanPageData;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class FlightInitPresenter
{
    public function __construct(private ?FlightPlanPageData $pageData) {}

    public function etdUtc(): ?string
    {
        $etdUtc = $this->pageData?->flightPlan->schedule->etdUtc;

        if ($etdUtc === null || preg_match('/(?:Z|\+00:00)\z/', $etdUtc) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($etdUtc)->utc()->format('Hi\Z');
        } catch (Throwable) {
            return null;
        }
    }

    public function rampFuel(): ?string
    {
        return $this->pageData?->flightPlan->fuelPlan?->ramp?->format();
    }

    public function acarsDate(): ?string
    {
        return $this->pageData?->flightPlan->flightInit?->acarsInitDate;
    }

    /** @return list<array{id: string, label: string, value: ?string}> */
    public function fields(): array
    {
        $flightPlan = $this->pageData?->flightPlan;

        return [
            ['id' => 'flight-init-tail-number', 'label' => 'Tail number', 'value' => $flightPlan?->identity->tailNumber],
            ['id' => 'flight-init-etd', 'label' => 'ETD (UTC)', 'value' => $this->etdUtc()],
            ['id' => 'flight-init-ramp-fuel', 'label' => 'Estimated ramp fuel', 'value' => $this->rampFuel()],
            ['id' => 'flight-init-flight-number', 'label' => 'Flight number', 'value' => $flightPlan?->identity->flightNumber],
            ['id' => 'flight-init-departure', 'label' => 'Departure', 'value' => $flightPlan?->route->departure->value],
            ['id' => 'flight-init-destination', 'label' => 'Destination', 'value' => $flightPlan?->route->destination->value],
            ['id' => 'flight-init-acars-init-date', 'label' => 'ACARS init date', 'value' => $this->acarsDate()],
        ];
    }
}
