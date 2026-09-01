<?php

namespace App\Services\FlightPlan;

use App\DTOs\Etops\EtopsAlternateData;
use App\DTOs\Etops\EtopsCoordinateData;
use App\DTOs\Etops\EtopsCriticalFuelData;
use App\DTOs\Etops\EtopsData;
use App\DTOs\Etops\EtopsDiversionData;
use App\DTOs\Etops\EtopsEqualTimePointData;
use App\DTOs\Etops\EtopsPointData;
use App\DTOs\Etops\EtopsScenarioData;
use App\Enums\EtopsApplicability;
use App\ValueObjects\AirportCode;
use App\ValueObjects\FuelQuantity;
use InvalidArgumentException;

class EtopsDataBuilder
{
    /** @param array<string, mixed> $source */
    public function fromExtracted(array $source): ?EtopsData
    {
        $applicability = is_string($source['applicability'] ?? null)
            ? EtopsApplicability::tryFrom($source['applicability'])
            : null;
        $ratingMinutes = is_int($source['rating_minutes'] ?? null) ? $source['rating_minutes'] : null;
        $entryPoint = $this->extractedPoint('EENT', $source['eent_coordinates'] ?? null, 0);
        $equalTimePoints = [];
        $scenarios = [];
        $sequence = 1;

        foreach ($source['etps'] ?? [] as $value) {
            if (! is_array($value)) {
                $sequence++;

                continue;
            }

            $label = $this->nullableString($value['label'] ?? null);
            $coordinate = $this->extractedCoordinate($value['coordinates'] ?? null);
            $airports = is_string($value['airports'] ?? null) ? explode('-', $value['airports']) : [];
            $scenario = $this->nullableString($value['scenario'] ?? null);

            if ($label === null || $coordinate === null || count($airports) !== 2 || $scenario === null) {
                $sequence++;

                continue;
            }

            try {
                $equalTimePoints[] = new EtopsEqualTimePointData(
                    label: $label,
                    coordinate: $coordinate,
                    sequence: $sequence,
                    firstAlternate: new AirportCode($airports[0]),
                    secondAlternate: new AirportCode($airports[1]),
                );
                $scenarios[] = new EtopsScenarioData($scenario, $label);
            } catch (InvalidArgumentException) {
                $sequence++;

                continue;
            }

            $sequence++;
        }

        $exitPoint = $this->extractedPoint('EEXP', $source['eexp_coordinates'] ?? null, $sequence);
        $applicability ??= EtopsApplicability::Unknown;

        if ($entryPoint === null
            && $equalTimePoints === []
            && $exitPoint === null
            && $ratingMinutes === null
            && $applicability === EtopsApplicability::Unknown) {
            return null;
        }

        return new EtopsData(
            sectionPresent: ($source['section_present'] ?? false) === true
                || $entryPoint !== null
                || $equalTimePoints !== []
                || $exitPoint !== null,
            applicability: $applicability,
            ratingMinutes: $ratingMinutes,
            entryPoint: $entryPoint,
            exitPoint: $exitPoint,
            equalTimePoints: $equalTimePoints,
            scenarios: $scenarios,
        );
    }

    public function fromSerialized(mixed $source): ?EtopsData
    {
        if (! is_array($source)) {
            return null;
        }

        $applicability = is_string($source['applicability'] ?? null)
            ? EtopsApplicability::tryFrom($source['applicability'])
            : null;

        return new EtopsData(
            sectionPresent: ($source['sectionPresent'] ?? false) === true,
            applicability: $applicability ?? EtopsApplicability::Unknown,
            ratingMinutes: is_int($source['ratingMinutes'] ?? null) && $source['ratingMinutes'] > 0
                ? $source['ratingMinutes']
                : null,
            entryPoint: $this->serializedPoint($source['entryPoint'] ?? null),
            exitPoint: $this->serializedPoint($source['exitPoint'] ?? null),
            equalTimePoints: $this->serializedEqualTimePoints($source['equalTimePoints'] ?? null),
            alternates: $this->serializedAlternates($source['alternates'] ?? null),
            scenarios: $this->serializedScenarios($source['scenarios'] ?? null),
        );
    }

    private function extractedPoint(string $label, mixed $coordinates, int $sequence): ?EtopsPointData
    {
        $coordinate = $this->extractedCoordinate($coordinates);

        return $coordinate === null ? null : new EtopsPointData($label, $coordinate, $sequence);
    }

    private function extractedCoordinate(mixed $value): ?EtopsCoordinateData
    {
        if (! is_string($value) || preg_match(
            '/^([NS]\d{1,2}\h+\d{1,2}(?:\.\d+)?)\h+([EW]\d{1,3}\h+\d{1,2}(?:\.\d+)?)$/i',
            trim($value),
            $matches,
        ) !== 1) {
            return null;
        }

        try {
            return new EtopsCoordinateData($matches[1], $matches[2]);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function serializedPoint(mixed $source): ?EtopsPointData
    {
        if (! is_array($source)) {
            return null;
        }

        $label = $this->nullableString($source['label'] ?? null);
        $coordinate = $this->serializedCoordinate($source['coordinate'] ?? null);
        $sequence = $source['sequence'] ?? null;

        if ($label === null || $coordinate === null || ! is_int($sequence)) {
            return null;
        }

        try {
            return new EtopsPointData($label, $coordinate, $sequence);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @return list<EtopsEqualTimePointData> */
    private function serializedEqualTimePoints(mixed $source): array
    {
        if (! is_array($source)) {
            return [];
        }

        $points = [];

        foreach ($source as $value) {
            if (! is_array($value)) {
                continue;
            }

            $label = $this->nullableString($value['label'] ?? null);
            $coordinate = $this->serializedCoordinate($value['coordinate'] ?? null);
            $sequence = $value['sequence'] ?? null;

            if ($label === null || $coordinate === null || ! is_int($sequence)) {
                continue;
            }

            try {
                $points[] = new EtopsEqualTimePointData(
                    label: $label,
                    coordinate: $coordinate,
                    sequence: $sequence,
                    firstAlternate: $this->airportCode($value['firstAlternate'] ?? null),
                    secondAlternate: $this->airportCode($value['secondAlternate'] ?? null),
                );
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $points;
    }

    /** @return list<EtopsAlternateData> */
    private function serializedAlternates(mixed $source): array
    {
        if (! is_array($source)) {
            return [];
        }

        $alternates = [];

        foreach ($source as $value) {
            if (! is_array($value)) {
                continue;
            }

            $airport = $this->airportCode($value['airport'] ?? null);

            if ($airport === null) {
                continue;
            }

            $alternates[] = new EtopsAlternateData(
                airport: $airport,
                coordinate: $this->serializedCoordinate($value['coordinate'] ?? null),
                remarks: $this->nullableString($value['remarks'] ?? null),
            );
        }

        return $alternates;
    }

    /** @return list<EtopsScenarioData> */
    private function serializedScenarios(mixed $source): array
    {
        if (! is_array($source)) {
            return [];
        }

        $scenarios = [];

        foreach ($source as $value) {
            if (! is_array($value)) {
                continue;
            }

            $name = $this->nullableString($value['name'] ?? null);

            if ($name === null) {
                continue;
            }

            try {
                $scenarios[] = new EtopsScenarioData(
                    name: $name,
                    equalTimePointLabel: $this->nullableString($value['equalTimePointLabel'] ?? null),
                    diversion: $this->serializedDiversion($value['diversion'] ?? null),
                    criticalFuel: $this->serializedCriticalFuel($value['criticalFuel'] ?? null),
                    remarks: $this->nullableString($value['remarks'] ?? null),
                );
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $scenarios;
    }

    private function serializedDiversion(mixed $source): ?EtopsDiversionData
    {
        if (! is_array($source)) {
            return null;
        }

        $alternate = $this->airportCode($source['alternate'] ?? null);

        if ($alternate === null) {
            return null;
        }

        try {
            return new EtopsDiversionData(
                alternate: $alternate,
                timeMinutes: $this->nonNegativeInteger($source['timeMinutes'] ?? null),
                distanceNauticalMiles: $this->nonNegativeInteger($source['distanceNauticalMiles'] ?? null),
                flightLevel: $this->nonNegativeInteger($source['flightLevel'] ?? null),
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function serializedCriticalFuel(mixed $source): ?EtopsCriticalFuelData
    {
        if (! is_array($source)) {
            return null;
        }

        $quantity = $this->fuelQuantity($source['quantity'] ?? null);

        return $quantity === null
            ? null
            : new EtopsCriticalFuelData($quantity, $this->nullableString($source['basis'] ?? null));
    }

    private function serializedCoordinate(mixed $source): ?EtopsCoordinateData
    {
        if (! is_array($source)) {
            return null;
        }

        $latitude = $this->nullableString($source['latitude'] ?? null);
        $longitude = $this->nullableString($source['longitude'] ?? null);

        if ($latitude === null || $longitude === null) {
            return null;
        }

        try {
            return new EtopsCoordinateData($latitude, $longitude);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function airportCode(mixed $value): ?AirportCode
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new AirportCode($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function fuelQuantity(mixed $value): ?FuelQuantity
    {
        if (! is_array($value)) {
            return null;
        }

        $amount = $value['amount'] ?? null;
        $unit = $value['unit'] ?? null;

        if ((! is_int($amount) && ! is_float($amount)) || ! is_string($unit)) {
            return null;
        }

        try {
            return new FuelQuantity($amount, $unit);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}
