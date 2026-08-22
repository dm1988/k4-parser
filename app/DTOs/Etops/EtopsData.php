<?php

namespace App\DTOs\Etops;

use App\Enums\EtopsApplicability;
use JsonSerializable;

final readonly class EtopsData implements JsonSerializable
{
    /**
     * @param  list<EtopsEqualTimePointData>  $equalTimePoints
     * @param  list<EtopsAlternateData>  $alternates
     * @param  list<EtopsScenarioData>  $scenarios
     */
    public function __construct(
        public bool $sectionPresent,
        public EtopsApplicability $applicability,
        public ?EtopsPointData $entryPoint = null,
        public ?EtopsPointData $exitPoint = null,
        public array $equalTimePoints = [],
        public array $alternates = [],
        public array $scenarios = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sectionPresent' => $this->sectionPresent,
            'applicability' => $this->applicability->value,
            'entryPoint' => $this->entryPoint?->toArray(),
            'exitPoint' => $this->exitPoint?->toArray(),
            'equalTimePoints' => array_map(
                static fn (EtopsEqualTimePointData $point): array => $point->toArray(),
                $this->equalTimePoints,
            ),
            'alternates' => array_map(
                static fn (EtopsAlternateData $alternate): array => $alternate->toArray(),
                $this->alternates,
            ),
            'scenarios' => array_map(
                static fn (EtopsScenarioData $scenario): array => $scenario->toArray(),
                $this->scenarios,
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
