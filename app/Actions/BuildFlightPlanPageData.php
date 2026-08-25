<?php

namespace App\Actions;

use App\DTOs\AirportData;
use App\DTOs\CrewMemberData;
use App\DTOs\EnvelopeData;
use App\DTOs\Etops\EtopsCoordinateData;
use App\DTOs\Etops\EtopsData;
use App\DTOs\Etops\EtopsEqualTimePointData;
use App\DTOs\Etops\EtopsPointData;
use App\DTOs\Etops\EtopsScenarioData;
use App\DTOs\FlightIdentityData;
use App\DTOs\FlightInitData;
use App\DTOs\FlightPlanData;
use App\DTOs\FuelPlanData;
use App\DTOs\MaintenanceItemData;
use App\DTOs\MaintenanceLogData;
use App\DTOs\RouteData;
use App\DTOs\ScheduleData;
use App\DTOs\WaypointData;
use App\Enums\AltitudeUnit;
use App\Enums\EtopsApplicability;
use App\Enums\MaintenanceItemType;
use App\Services\FlightPlan\FlightInitFieldNormalizer;
use App\ValueObjects\AirportCode;
use App\ValueObjects\FuelQuantity;
use App\ValueObjects\InitialAltitude;
use App\ValueObjects\WeightQuantity;
use App\View\Models\FlightPlanPageData;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;
use ValueError;

class BuildFlightPlanPageData
{
    public function __construct(
        private readonly FlightInitFieldNormalizer $flightInitFieldNormalizer = new FlightInitFieldNormalizer,
    ) {}

    /** @param array<string, mixed>|null $result */
    public function handle(?array $result): ?FlightPlanPageData
    {
        $normalized = $result['flight_plan_data'] ?? null;

        if (! is_array($normalized)) {
            return null;
        }

        try {
            $flightPlan = $this->flightPlan($normalized);
        } catch (InvalidArgumentException|ValueError) {
            return null;
        }

        return new FlightPlanPageData(
            flightPlan: $flightPlan,
            departureAirport: $this->airport($result['departure_airport'] ?? null),
            destinationAirport: $this->airport($result['destination_airport'] ?? null),
            alternateAirport: $this->airport($result['alternate_airport'] ?? null),
            duration: $this->nullableString($result['duration'] ?? null),
        );
    }

    /** @param array<string, mixed> $data */
    private function flightPlan(array $data): FlightPlanData
    {
        $identity = $this->requiredArray($data, 'identity');
        $schedule = $this->requiredArray($data, 'schedule');
        $route = $this->requiredArray($data, 'route');
        $fuelPlan = $data['fuelPlan'] ?? null;

        return new FlightPlanData(
            identity: new FlightIdentityData(
                flightNumber: $this->nullableString($identity['flightNumber'] ?? null),
                tripNumber: $this->nullableString($identity['tripNumber'] ?? null),
                recallNumber: $this->nullableString($identity['recallNumber'] ?? null),
                aircraftType: $this->nullableString($identity['aircraftType'] ?? null),
                tailNumber: $this->nullableString($identity['tailNumber'] ?? null),
                flightDate: $this->flightDate($identity['flightDate'] ?? null),
                releaseRevision: $this->nullableString($identity['releaseRevision'] ?? null),
            ),
            schedule: ScheduleData::fromArray($schedule),
            route: new RouteData(
                departure: new AirportCode($this->requiredString($route, 'departure')),
                destination: new AirportCode($this->requiredString($route, 'destination')),
                alternate: $this->airportCode($route['alternate'] ?? null),
                route: $this->nullableString($route['route'] ?? null),
                departureRunway: $this->nullableString($route['departureRunway'] ?? null),
                arrivalRunway: $this->nullableString($route['arrivalRunway'] ?? null),
                departureSid: $this->nullableString($route['departureSid'] ?? null),
                arrivalStar: $this->nullableString($route['arrivalStar'] ?? null),
                distanceNauticalMiles: is_int($route['distanceNauticalMiles'] ?? null)
                    ? $route['distanceNauticalMiles']
                    : null,
            ),
            fuelPlan: is_array($fuelPlan) ? $this->fuelPlan($fuelPlan) : null,
            maintenanceLog: $this->maintenanceLog($data['maintenanceLog'] ?? null),
            envelope: $this->envelope($data['envelope'] ?? null),
            flightInit: $this->flightInit($data['flightInit'] ?? null),
            etops: $this->etops($data['etops'] ?? null),
            crewMembers: $this->crewMembers($data['crewMembers'] ?? null),
            waypoints: $this->waypoints($data['waypoints'] ?? null),
        );
    }

    private function flightDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (InvalidFormatException) {
            return null;
        }

        return $date !== null && $date->toDateString() === $value ? $date : null;
    }

    /** @param array<string, mixed> $data */
    private function fuelPlan(array $data): ?FuelPlanData
    {
        $quantities = [
            'ramp' => $this->fuelQuantity($data['ramp'] ?? null),
            'taxi' => $this->fuelQuantity($data['taxi'] ?? null),
            'takeoff' => $this->fuelQuantity($data['takeoff'] ?? null),
            'trip' => $this->fuelQuantity($data['trip'] ?? null),
            'contingency' => $this->fuelQuantity($data['contingency'] ?? null),
            'alternate' => $this->fuelQuantity($data['alternate'] ?? null),
            'finalReserve' => $this->fuelQuantity($data['finalReserve'] ?? null),
            'estimatedLanding' => $this->fuelQuantity($data['estimatedLanding'] ?? null),
        ];

        if (array_filter($quantities, static fn (?FuelQuantity $quantity): bool => $quantity !== null) === []) {
            return null;
        }

        return new FuelPlanData(...$quantities);
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

    /** @return list<WaypointData> */
    private function waypoints(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $waypoints = [];

        foreach ($value as $waypoint) {
            if (! is_array($waypoint)) {
                continue;
            }

            $identifier = $this->nullableString($waypoint['identifier'] ?? null);
            $coordinate = $this->nullableString($waypoint['coordinate'] ?? null);

            if ($identifier === null || $coordinate === null) {
                continue;
            }

            $waypoints[] = new WaypointData(
                identifier: $identifier,
                coordinate: $coordinate,
                legDurationMinutes: $this->nonNegativeInteger($waypoint['legDurationMinutes'] ?? null),
                cumulativeDurationMinutes: $this->nonNegativeInteger($waypoint['cumulativeDurationMinutes'] ?? null),
                remainingFuel: $this->fuelQuantity($waypoint['remainingFuel'] ?? null),
            );
        }

        return $waypoints;
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    private function airportCode(mixed $value): ?AirportCode
    {
        return is_string($value) && $value !== '' ? new AirportCode($value) : null;
    }

    private function maintenanceLog(mixed $value): ?MaintenanceLogData
    {
        if (! is_array($value)) {
            return null;
        }

        $applicability = is_string($value['etopsApplicability'] ?? null)
            ? EtopsApplicability::tryFrom($value['etopsApplicability'])
            : null;

        return new MaintenanceLogData(
            sectionPresent: ($value['sectionPresent'] ?? false) === true,
            etopsApplicability: $applicability ?? EtopsApplicability::Unknown,
            items: $this->maintenanceItems($value['items'] ?? null),
        );
    }

    private function envelope(mixed $value): ?EnvelopeData
    {
        if (! is_array($value) || ($value['sectionPresent'] ?? false) !== true) {
            return null;
        }

        return new EnvelopeData(
            sectionPresent: true,
            sourceType: $this->nullableString($value['sourceType'] ?? null) ?? 'takeoff_landing_report',
            reportReference: $this->nullableString($value['reportReference'] ?? null),
            airport: $this->nullableString($value['airport'] ?? null),
            plannedRunway: $this->nullableString($value['plannedRunway'] ?? null),
            outsideAirTemperatureCelsius: $this->nullableFloat($value['outsideAirTemperatureCelsius'] ?? null),
            wind: $this->nullableString($value['wind'] ?? null),
            qnhInchesMercury: $this->nullableFloat($value['qnhInchesMercury'] ?? null),
            qnhHectopascals: $this->nullableInteger($value['qnhHectopascals'] ?? null),
            maximumRunwayTakeoffWeight: $this->weightQuantity($value['maximumRunwayTakeoffWeight'] ?? null),
            flapSetting: $this->nullableString($value['flapSetting'] ?? null),
            antiIce: is_bool($value['antiIce'] ?? null) ? $value['antiIce'] : null,
            v1Knots: $this->nullableInteger($value['v1Knots'] ?? null),
            rotateKnots: $this->nullableInteger($value['rotateKnots'] ?? null),
            v2Knots: $this->nullableInteger($value['v2Knots'] ?? null),
            plannedTakeoffWeight: $this->weightQuantity($value['plannedTakeoffWeight'] ?? null),
            maximumFieldTakeoffWeight: $this->weightQuantity($value['maximumFieldTakeoffWeight'] ?? null),
            sourceWarnings: $this->strings($value['sourceWarnings'] ?? null),
        );
    }

    private function weightQuantity(mixed $value): ?WeightQuantity
    {
        if (! is_array($value) || ! is_int($value['amount'] ?? null) || ! is_string($value['unit'] ?? null)) {
            return null;
        }

        try {
            return new WeightQuantity($value['amount'], $value['unit']);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function flightInit(mixed $value): ?FlightInitData
    {
        if (! is_array($value) || ($value['sectionPresent'] ?? false) !== true) {
            return null;
        }

        return new FlightInitData(
            sectionPresent: true,
            acarsInitDate: $this->flightInitFieldNormalizer->acarsInitDate($value['acarsInitDate'] ?? null),
            filedInitialAltitude: $this->initialAltitude($value['filedInitialAltitude'] ?? null),
            fmsInitialAltitude: $this->initialAltitude($value['fmsInitialAltitude'] ?? null),
        );
    }

    private function initialAltitude(mixed $value): ?InitialAltitude
    {
        if (! is_array($value)
            || ! is_int($value['value'] ?? null)
            || ! is_string($value['unit'] ?? null)
            || ! is_bool($value['isFlightLevel'] ?? null)) {
            return null;
        }

        try {
            return new InitialAltitude(
                value: $value['value'],
                unit: AltitudeUnit::from($value['unit']),
                isFlightLevel: $value['isFlightLevel'],
            );
        } catch (InvalidArgumentException|ValueError) {
            return null;
        }
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_float($value) || is_int($value) ? (float) $value : null;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    /** @return list<string> */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $value): ?string => $this->nullableString($value),
            $values,
        )));
    }

    /** @return list<MaintenanceItemData> */
    private function maintenanceItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = is_string($item['type'] ?? null)
                ? MaintenanceItemType::tryFrom($item['type'])
                : null;
            $number = $this->nullableString($item['number'] ?? null);
            $description = $this->nullableString($item['description'] ?? null);

            if ($type === null || $number === null || $description === null) {
                continue;
            }

            $normalized[] = new MaintenanceItemData(
                type: $type,
                number: $number,
                description: $description,
                reference: $this->nullableString($item['reference'] ?? null),
                status: $this->nullableString($item['status'] ?? null),
                limitations: $this->nullableString($item['limitations'] ?? null),
                procedures: $this->nullableString($item['procedures'] ?? null),
            );
        }

        return $normalized;
    }

    /** @return list<CrewMemberData> */
    private function crewMembers(mixed $members): array
    {
        if (! is_array($members)) {
            return [];
        }

        $normalized = [];

        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            $name = $this->nullableString($member['name'] ?? null);

            if ($name === null) {
                continue;
            }

            $normalized[] = new CrewMemberData(
                name: $name,
                role: $this->nullableString($member['role'] ?? null),
                base: $this->nullableString($member['base'] ?? null),
                employeeNumber: $this->flightInitFieldNormalizer->employeeNumber($member['employeeNumber'] ?? null),
            );
        }

        return $normalized;
    }

    private function airport(mixed $value): ?AirportData
    {
        return is_array($value) ? AirportData::fromArray($value) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function requiredArray(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            throw new InvalidArgumentException("Flight plan {$key} data is required.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $key): string
    {
        $value = $this->nullableString($data[$key] ?? null);

        if ($value === null) {
            throw new InvalidArgumentException("Flight plan {$key} is required.");
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function etops(mixed $value): ?EtopsData
    {
        if (! is_array($value)) {
            return null;
        }

        $applicability = is_string($value['applicability'] ?? null)
            ? EtopsApplicability::tryFrom($value['applicability'])
            : null;
        $equalTimePoints = [];
        $scenarios = [];
        $equalTimePointValues = is_array($value['equalTimePoints'] ?? null) ? $value['equalTimePoints'] : [];
        $scenarioValues = is_array($value['scenarios'] ?? null) ? $value['scenarios'] : [];

        foreach ($equalTimePointValues as $etp) {
            if (! is_array($etp)) {
                continue;
            }

            $label = $this->nullableString($etp['label'] ?? null);
            $coordinate = $this->etopsCoordinate($etp['coordinate'] ?? null);
            $sequence = $etp['sequence'] ?? null;

            if ($label === null || $coordinate === null || ! is_int($sequence)) {
                continue;
            }

            try {
                $equalTimePoints[] = new EtopsEqualTimePointData(
                    label: $label,
                    coordinate: $coordinate,
                    sequence: $sequence,
                    firstAlternate: $this->airportCode($etp['firstAlternate'] ?? null),
                    secondAlternate: $this->airportCode($etp['secondAlternate'] ?? null),
                );
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        foreach ($scenarioValues as $scenario) {
            if (! is_array($scenario)) {
                continue;
            }

            $name = $this->nullableString($scenario['name'] ?? null);

            if ($name === null) {
                continue;
            }

            $scenarios[] = new EtopsScenarioData(
                name: $name,
                equalTimePointLabel: $this->nullableString($scenario['equalTimePointLabel'] ?? null),
                remarks: $this->nullableString($scenario['remarks'] ?? null),
            );
        }

        return new EtopsData(
            sectionPresent: ($value['sectionPresent'] ?? false) === true,
            applicability: $applicability ?? EtopsApplicability::Unknown,
            entryPoint: $this->etopsPoint($value['entryPoint'] ?? null),
            exitPoint: $this->etopsPoint($value['exitPoint'] ?? null),
            equalTimePoints: $equalTimePoints,
            scenarios: $scenarios,
        );
    }

    private function etopsPoint(mixed $value): ?EtopsPointData
    {
        if (! is_array($value)) {
            return null;
        }

        $label = $this->nullableString($value['label'] ?? null);
        $coordinate = $this->etopsCoordinate($value['coordinate'] ?? null);
        $sequence = $value['sequence'] ?? null;

        if ($label === null || $coordinate === null || ! is_int($sequence)) {
            return null;
        }

        try {
            return new EtopsPointData($label, $coordinate, $sequence);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function etopsCoordinate(mixed $value): ?EtopsCoordinateData
    {
        if (! is_array($value)) {
            return null;
        }

        $latitude = $this->nullableString($value['latitude'] ?? null);
        $longitude = $this->nullableString($value['longitude'] ?? null);

        if ($latitude === null || $longitude === null) {
            return null;
        }

        try {
            return new EtopsCoordinateData($latitude, $longitude);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
