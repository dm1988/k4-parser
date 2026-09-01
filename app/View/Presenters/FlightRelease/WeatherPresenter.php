<?php

namespace App\View\Presenters\FlightRelease;

use App\View\Models\FlightPlanPageData;

final readonly class WeatherPresenter
{
    public function __construct(private ?FlightPlanPageData $pageData) {}

    /** @return list<array{role: string, airport: ?string, metars: list<string>, tafs: list<string>}> */
    public function airportGroups(): array
    {
        $weather = $this->pageData?->flightPlan->weather;
        $route = $this->pageData?->flightPlan->route;

        return [
            [
                'role' => 'Departure',
                'airport' => $weather?->departure?->airport->value ?? $route?->departure->value,
                'metars' => $weather?->departure->metars ?? [],
                'tafs' => $weather?->departure->tafs ?? [],
            ],
            [
                'role' => 'Destination',
                'airport' => $weather?->destination?->airport->value ?? $route?->destination->value,
                'metars' => $weather?->destination->metars ?? [],
                'tafs' => $weather?->destination->tafs ?? [],
            ],
            [
                'role' => 'Alternate',
                'airport' => $weather?->alternate?->airport->value ?? $route?->alternate?->value,
                'metars' => $weather?->alternate->metars ?? [],
                'tafs' => $weather?->alternate->tafs ?? [],
            ],
        ];
    }

    public function raim(): ?string
    {
        return $this->pageData?->flightPlan->weather?->raim;
    }
}
