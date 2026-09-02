<?php

namespace App\DTOs\Etops;

use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;

final readonly class EtopsCoordinateData implements JsonSerializable
{
    public string $latitude;

    public string $longitude;

    public function __construct(string $latitude, string $longitude)
    {
        $latitude = Str::upper(Str::squish($latitude));
        $longitude = Str::upper(Str::squish($longitude));

        if (! $this->isValidCoordinate($latitude, 90, '[NS]', 2)) {
            throw new InvalidArgumentException('ETOPS latitude must use a valid degrees-and-minutes coordinate.');
        }

        if (! $this->isValidCoordinate($longitude, 180, '[EW]', 3)) {
            throw new InvalidArgumentException('ETOPS longitude must use a valid degrees-and-minutes coordinate.');
        }

        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /** @return array{latitude: string, longitude: string} */
    public function toArray(): array
    {
        return ['latitude' => $this->latitude, 'longitude' => $this->longitude];
    }

    /** @return array{latitude: string, longitude: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function isValidCoordinate(string $coordinate, int $maximumDegrees, string $hemispheres, int $degreeDigits): bool
    {
        $pattern = sprintf('/^%s\h*(\d{1,%d})\h+(\d{1,2}(?:\.\d+)?)$/', $hemispheres, $degreeDigits);

        if (preg_match($pattern, $coordinate, $matches) !== 1) {
            return false;
        }

        $degrees = (int) $matches[1];
        $minutes = (float) $matches[2];

        return $degrees <= $maximumDegrees
            && $minutes < 60
            && ($degrees < $maximumDegrees || $minutes === 0.0);
    }
}
