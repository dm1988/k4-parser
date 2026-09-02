<?php

namespace App\DTOs\Etops;

use App\ValueObjects\AirportCode;
use Illuminate\Support\Str;
use JsonSerializable;

final readonly class EtopsAlternateData implements JsonSerializable
{
    public ?string $remarks;

    public function __construct(
        public AirportCode $airport,
        public ?EtopsCoordinateData $coordinate = null,
        ?string $remarks = null,
    ) {
        $remarks = $remarks === null ? null : Str::squish($remarks);
        $this->remarks = $remarks === '' ? null : $remarks;
    }

    /** @return array{airport: string, coordinate: array{latitude: string, longitude: string}|null, remarks: ?string} */
    public function toArray(): array
    {
        return [
            'airport' => $this->airport->value,
            'coordinate' => $this->coordinate?->toArray(),
            'remarks' => $this->remarks,
        ];
    }

    /** @return array{airport: string, coordinate: array{latitude: string, longitude: string}|null, remarks: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
