<?php

namespace App\DTOs\Etops;

use App\ValueObjects\AirportCode;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;

final readonly class EtopsEqualTimePointData implements JsonSerializable
{
    public string $label;

    public function __construct(
        string $label,
        public EtopsCoordinateData $coordinate,
        public int $sequence,
        public ?AirportCode $firstAlternate = null,
        public ?AirportCode $secondAlternate = null,
    ) {
        $label = Str::squish($label);

        if ($label === '') {
            throw new InvalidArgumentException('ETOPS equal-time-point label must not be empty.');
        }

        if ($sequence < 0) {
            throw new InvalidArgumentException('ETOPS equal-time-point sequence must not be negative.');
        }

        $this->label = $label;
    }

    /** @return array{label: string, coordinate: array{latitude: string, longitude: string}, sequence: int, firstAlternate: ?string, secondAlternate: ?string} */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'coordinate' => $this->coordinate->toArray(),
            'sequence' => $this->sequence,
            'firstAlternate' => $this->firstAlternate?->value,
            'secondAlternate' => $this->secondAlternate?->value,
        ];
    }

    /** @return array{label: string, coordinate: array{latitude: string, longitude: string}, sequence: int, firstAlternate: ?string, secondAlternate: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
