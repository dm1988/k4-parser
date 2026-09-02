<?php

namespace App\View\Presenters\FlightRelease;

use App\DTOs\TakeoffLandingReportData;
use App\ValueObjects\WeightQuantity;
use App\View\Models\FlightPlanPageData;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

final readonly class TakeoffLandingReportPresenter
{
    public function __construct(private ?FlightPlanPageData $pageData) {}

    public function sourceLabel(): string
    {
        return match ($this->report()?->sourceType) {
            'takeoff_landing_report' => 'Takeoff and Landing Report',
            default => 'Confirmed release section',
        };
    }

    public function reportReference(): ?string
    {
        return $this->report()?->reportReference;
    }

    public function airport(): ?string
    {
        return $this->report()?->airport;
    }

    public function plannedRunway(): ?string
    {
        return $this->report()?->plannedRunway;
    }

    public function outsideAirTemperature(): ?string
    {
        $temperature = $this->report()?->outsideAirTemperatureCelsius;

        return $temperature === null ? null : Number::format($temperature, precision: 1).' °C';
    }

    public function wind(): ?string
    {
        return $this->report()?->wind;
    }

    public function qnh(): ?string
    {
        $report = $this->report();

        if ($report?->qnhHectopascals !== null) {
            return $report->qnhHectopascals.' hPa';
        }

        return $report?->qnhInchesMercury === null
            ? null
            : number_format($report->qnhInchesMercury, 2).' inHg';
    }

    public function flapSetting(): ?string
    {
        return $this->report()?->flapSetting;
    }

    public function antiIce(): ?string
    {
        $antiIce = $this->report()?->antiIce;

        return $antiIce === null ? null : ($antiIce ? 'Yes' : 'No');
    }

    public function maximumRunwayTakeoffWeight(): ?string
    {
        return $this->formatWeight($this->report()?->maximumRunwayTakeoffWeight);
    }

    public function maximumFieldTakeoffWeight(): ?string
    {
        return $this->formatWeight($this->report()?->maximumFieldTakeoffWeight);
    }

    public function plannedTakeoffWeight(): ?string
    {
        return $this->formatWeight($this->report()?->plannedTakeoffWeight);
    }

    public function v1(): ?string
    {
        return $this->formatSpeed($this->report()?->v1Knots);
    }

    public function rotateSpeed(): ?string
    {
        return $this->formatSpeed($this->report()?->rotateKnots);
    }

    public function v2(): ?string
    {
        return $this->formatSpeed($this->report()?->v2Knots);
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->report()->sourceWarnings ?? [];
    }

    private function report(): ?TakeoffLandingReportData
    {
        return $this->pageData?->flightPlan->takeoffLandingReport;
    }

    private function formatWeight(?WeightQuantity $weight): ?string
    {
        return $weight === null ? null : Number::format($weight->amount).' '.Str::upper($weight->unit);
    }

    private function formatSpeed(?int $speed): ?string
    {
        return $speed === null ? null : $speed.' kt';
    }
}
