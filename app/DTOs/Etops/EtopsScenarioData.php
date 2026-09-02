<?php

namespace App\DTOs\Etops;

use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;

final readonly class EtopsScenarioData implements JsonSerializable
{
    public string $name;

    public ?string $equalTimePointLabel;

    public ?string $remarks;

    public function __construct(
        string $name,
        ?string $equalTimePointLabel = null,
        public ?EtopsDiversionData $diversion = null,
        public ?EtopsCriticalFuelData $criticalFuel = null,
        ?string $remarks = null,
    ) {
        $name = Str::squish($name);

        if ($name === '') {
            throw new InvalidArgumentException('ETOPS scenario name must not be empty.');
        }

        $equalTimePointLabel = $equalTimePointLabel === null ? null : Str::squish($equalTimePointLabel);
        $remarks = $remarks === null ? null : Str::squish($remarks);

        $this->name = $name;
        $this->equalTimePointLabel = $equalTimePointLabel === '' ? null : $equalTimePointLabel;
        $this->remarks = $remarks === '' ? null : $remarks;
    }

    /** @return array{name: string, equalTimePointLabel: ?string, diversion: array{alternate: string, timeMinutes: ?int, distanceNauticalMiles: ?int, flightLevel: ?int}|null, criticalFuel: array{quantity: array{amount: float, unit: 'kg'|'lb'}, basis: ?string}|null, remarks: ?string} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'equalTimePointLabel' => $this->equalTimePointLabel,
            'diversion' => $this->diversion?->toArray(),
            'criticalFuel' => $this->criticalFuel?->toArray(),
            'remarks' => $this->remarks,
        ];
    }

    /** @return array{name: string, equalTimePointLabel: ?string, diversion: array{alternate: string, timeMinutes: ?int, distanceNauticalMiles: ?int, flightLevel: ?int}|null, criticalFuel: array{quantity: array{amount: float, unit: 'kg'|'lb'}, basis: ?string}|null, remarks: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
