<?php

namespace App\DTOs\Weather;

use App\ValueObjects\AirportCode;
use JsonSerializable;

final readonly class AirportWeatherData implements JsonSerializable
{
    /**
     * @param  list<string>  $metars
     * @param  list<string>  $tafs
     */
    public function __construct(
        public AirportCode $airport,
        public array $metars = [],
        public array $tafs = [],
    ) {}

    /** @return array{airport: string, metars: list<string>, tafs: list<string>} */
    public function toArray(): array
    {
        return [
            'airport' => $this->airport->value,
            'metars' => $this->metars,
            'tafs' => $this->tafs,
        ];
    }

    /** @return array{airport: string, metars: list<string>, tafs: list<string>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
