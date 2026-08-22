<?php

namespace App\DTOs\Etops;

use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;

final readonly class EtopsPointData implements JsonSerializable
{
    public string $label;

    public function __construct(
        string $label,
        public EtopsCoordinateData $coordinate,
        public int $sequence,
    ) {
        $label = Str::squish($label);

        if ($label === '') {
            throw new InvalidArgumentException('ETOPS point label must not be empty.');
        }

        if ($sequence < 0) {
            throw new InvalidArgumentException('ETOPS point sequence must not be negative.');
        }

        $this->label = $label;
    }

    /** @return array{label: string, coordinate: array{latitude: string, longitude: string}, sequence: int} */
    public function toArray(): array
    {
        return ['label' => $this->label, 'coordinate' => $this->coordinate->toArray(), 'sequence' => $this->sequence];
    }

    /** @return array{label: string, coordinate: array{latitude: string, longitude: string}, sequence: int} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
