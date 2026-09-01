<?php

namespace App\DTOs;

use App\ValueObjects\WeightQuantity;
use JsonSerializable;

final readonly class TakeoffLandingReportData implements JsonSerializable
{
    /** @param list<string> $sourceWarnings */
    public function __construct(
        public bool $sectionPresent,
        public string $sourceType,
        public ?string $reportReference = null,
        public ?string $airport = null,
        public ?string $plannedRunway = null,
        public ?float $outsideAirTemperatureCelsius = null,
        public ?string $wind = null,
        public ?float $qnhInchesMercury = null,
        public ?int $qnhHectopascals = null,
        public ?WeightQuantity $maximumRunwayTakeoffWeight = null,
        public ?string $flapSetting = null,
        public ?bool $antiIce = null,
        public ?int $v1Knots = null,
        public ?int $rotateKnots = null,
        public ?int $v2Knots = null,
        public ?WeightQuantity $plannedTakeoffWeight = null,
        public ?WeightQuantity $maximumFieldTakeoffWeight = null,
        public array $sourceWarnings = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sectionPresent' => $this->sectionPresent,
            'sourceType' => $this->sourceType,
            'reportReference' => $this->reportReference,
            'airport' => $this->airport,
            'plannedRunway' => $this->plannedRunway,
            'outsideAirTemperatureCelsius' => $this->outsideAirTemperatureCelsius,
            'wind' => $this->wind,
            'qnhInchesMercury' => $this->qnhInchesMercury,
            'qnhHectopascals' => $this->qnhHectopascals,
            'maximumRunwayTakeoffWeight' => $this->maximumRunwayTakeoffWeight?->toArray(),
            'flapSetting' => $this->flapSetting,
            'antiIce' => $this->antiIce,
            'v1Knots' => $this->v1Knots,
            'rotateKnots' => $this->rotateKnots,
            'v2Knots' => $this->v2Knots,
            'plannedTakeoffWeight' => $this->plannedTakeoffWeight?->toArray(),
            'maximumFieldTakeoffWeight' => $this->maximumFieldTakeoffWeight?->toArray(),
            'sourceWarnings' => $this->sourceWarnings,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
