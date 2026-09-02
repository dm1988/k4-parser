<?php

namespace App\View\Presenters\FlightRelease;

use App\DTOs\Etops\EtopsEqualTimePointData;
use App\DTOs\Etops\EtopsScenarioData;
use App\Enums\EtopsApplicability;
use App\View\Models\FlightPlanPageData;

final readonly class EtopsPresenter
{
    public function __construct(private ?FlightPlanPageData $pageData) {}

    public function applicability(): EtopsApplicability
    {
        return $this->pageData?->flightPlan->etops->applicability ?? EtopsApplicability::Unknown;
    }

    public function hasData(): bool
    {
        return $this->pageData?->hasEtopsData() ?? false;
    }

    public function overviewSummary(): ?string
    {
        if (! $this->hasData()) {
            return null;
        }

        $summary = [];
        $criticalPointCount = count($this->criticalPoints());

        if ($criticalPointCount > 0) {
            $summary[] = $criticalPointCount.' critical '.($criticalPointCount === 1 ? 'point' : 'points');
        }

        if ($this->entryCoordinates() !== null) {
            $summary[] = 'EENT';
        }

        if ($this->exitCoordinates() !== null) {
            $summary[] = 'EEXP';
        }

        return implode(' · ', $summary);
    }

    /** @return list<array{label: string, airports: string, coordinates: string, scenario: string}> */
    public function criticalPoints(): array
    {
        $etops = $this->pageData?->flightPlan->etops;

        if ($etops === null) {
            return [];
        }

        return array_map(
            static function (EtopsEqualTimePointData $point, int $index) use ($etops): array {
                $scenario = $etops->scenarios[$index] ?? null;

                return [
                    'label' => $point->label,
                    'airports' => implode('-', array_filter([
                        $point->firstAlternate?->value,
                        $point->secondAlternate?->value,
                    ])),
                    'coordinates' => $point->coordinate->latitude.' '.$point->coordinate->longitude,
                    'scenario' => $scenario->name ?? '',
                ];
            },
            $etops->equalTimePoints,
            array_keys($etops->equalTimePoints),
        );
    }

    public function applicabilityLabel(): string
    {
        return $this->pageData?->flightPlan->etops?->applicability->label() ?? 'Not confirmed';
    }

    /** @return list<array{label: string, coordinates: string}> */
    public function boundaryPoints(): array
    {
        $etops = $this->pageData?->flightPlan->etops;
        $points = [];

        foreach ([$etops?->entryPoint, $etops?->exitPoint] as $point) {
            if ($point !== null) {
                $points[] = [
                    'label' => $point->label,
                    'coordinates' => $point->coordinate->latitude.' '.$point->coordinate->longitude,
                ];
            }
        }

        return $points;
    }

    /** @return list<string> */
    public function alternates(): array
    {
        $etops = $this->pageData?->flightPlan->etops;

        if ($etops === null) {
            return [];
        }

        $alternates = [];

        foreach ($etops->equalTimePoints as $point) {
            foreach ([$point->firstAlternate, $point->secondAlternate] as $alternate) {
                if ($alternate !== null) {
                    $alternates[$alternate->value] = $alternate->value;
                }
            }
        }

        return array_values($alternates);
    }

    /** @return list<array{name: string, equalTimePointLabel: ?string}> */
    public function scenarios(): array
    {
        return array_map(
            static fn (EtopsScenarioData $scenario): array => [
                'name' => $scenario->name,
                'equalTimePointLabel' => $scenario->equalTimePointLabel,
            ],
            $this->pageData?->flightPlan->etops->scenarios ?? [],
        );
    }

    /**
     * @param  array{label: string, airports: string, coordinates: string, scenario: string}  $etp
     * @return list<string>
     */
    public function airports(array $etp): array
    {
        return array_values(array_filter(
            explode('-', $etp['airports']),
            static fn (string $airport): bool => $airport !== '',
        ));
    }

    public function entryCoordinates(): ?string
    {
        $coordinate = $this->pageData?->flightPlan->etops?->entryPoint?->coordinate;

        return $coordinate === null ? null : $coordinate->latitude.' '.$coordinate->longitude;
    }

    public function exitCoordinates(): ?string
    {
        $coordinate = $this->pageData?->flightPlan->etops?->exitPoint?->coordinate;

        return $coordinate === null ? null : $coordinate->latitude.' '.$coordinate->longitude;
    }

    public function badgeLabel(): ?string
    {
        $etops = $this->pageData?->flightPlan->etops;

        if ($etops?->applicability !== EtopsApplicability::ConfirmedEtops || $etops->ratingMinutes === null) {
            return null;
        }

        return 'ETOPS '.$etops->ratingMinutes;
    }
}
