<?php

namespace App\DTOs\Etops;

use App\ValueObjects\AirportCode;
use InvalidArgumentException;
use JsonSerializable;

final readonly class EtopsDiversionData implements JsonSerializable
{
    public function __construct(
        public AirportCode $alternate,
        public ?int $timeMinutes = null,
        public ?int $distanceNauticalMiles = null,
        public ?int $flightLevel = null,
    ) {
        if ($timeMinutes !== null && $timeMinutes < 0) {
            throw new InvalidArgumentException('ETOPS diversion time must not be negative.');
        }

        if ($distanceNauticalMiles !== null && $distanceNauticalMiles < 0) {
            throw new InvalidArgumentException('ETOPS diversion distance must not be negative.');
        }

        if ($flightLevel !== null && $flightLevel < 0) {
            throw new InvalidArgumentException('ETOPS diversion flight level must not be negative.');
        }
    }

    /** @return array{alternate: string, timeMinutes: ?int, distanceNauticalMiles: ?int, flightLevel: ?int} */
    public function toArray(): array
    {
        return [
            'alternate' => $this->alternate->value,
            'timeMinutes' => $this->timeMinutes,
            'distanceNauticalMiles' => $this->distanceNauticalMiles,
            'flightLevel' => $this->flightLevel,
        ];
    }

    /** @return array{alternate: string, timeMinutes: ?int, distanceNauticalMiles: ?int, flightLevel: ?int} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
