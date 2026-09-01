<?php

namespace App\Actions;

use App\DTOs\AirportData;
use App\DTOs\FlightIdentityData;
use App\DTOs\FlightInitData;
use App\DTOs\FlightPlanData;
use App\DTOs\FuelPlanData;
use App\DTOs\GeneralDeclarationData;
use App\DTOs\ReleaseAuthorizationData;
use App\DTOs\RouteData;
use App\DTOs\ScheduleData;
use App\Enums\AltitudeUnit;
use App\Enums\OperationsSpecification;
use App\Services\FlightPlan\CrewMemberDataBuilder;
use App\Services\FlightPlan\EtopsDataBuilder;
use App\Services\FlightPlan\FlightInitFieldNormalizer;
use App\Services\FlightPlan\FuelPlanFieldNormalizer;
use App\Services\FlightPlan\MaintenanceLogDataBuilder;
use App\Services\FlightPlan\TakeoffLandingReportDataBuilder;
use App\Services\FlightPlan\WaypointDataBuilder;
use App\Services\FlightPlan\WeatherDataBuilder;
use App\Services\FlightPlan\WeightBalanceDataBuilder;
use App\ValueObjects\AirportCode;
use App\ValueObjects\FuelQuantity;
use App\ValueObjects\InitialAltitude;
use App\View\Models\FlightPlanPageData;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;
use ValueError;

class BuildFlightPlanPageData
{
    public function __construct(
        private readonly FlightInitFieldNormalizer $flightInitFieldNormalizer,
        private readonly FuelPlanFieldNormalizer $fuelPlanFieldNormalizer,
        private readonly EtopsDataBuilder $etopsDataBuilder,
        private readonly MaintenanceLogDataBuilder $maintenanceLogDataBuilder,
        private readonly WeatherDataBuilder $weatherDataBuilder,
        private readonly CrewMemberDataBuilder $crewMemberDataBuilder,
        private readonly WaypointDataBuilder $waypointDataBuilder,
        private readonly TakeoffLandingReportDataBuilder $takeoffLandingReportDataBuilder,
        private readonly WeightBalanceDataBuilder $weightBalanceDataBuilder,
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
            maintenanceLog: $this->maintenanceLogDataBuilder->fromSerialized($data['maintenanceLog'] ?? null),
            takeoffLandingReport: $this->takeoffLandingReportDataBuilder->fromSerialized(
                $data['takeoffLandingReport'] ?? $data['envelope'] ?? null,
            ),
            flightInit: $this->flightInit($data['flightInit'] ?? null),
            etops: $this->etopsDataBuilder->fromSerialized($data['etops'] ?? null),
            weather: $this->weatherDataBuilder->fromSerialized($data['weather'] ?? null),
            weightBalance: $this->weightBalanceDataBuilder->fromSerialized($data['weightBalance'] ?? null),
            generalDeclaration: $this->generalDeclaration($data['generalDeclaration'] ?? null),
            releaseAuthorization: $this->releaseAuthorization($data['releaseAuthorization'] ?? null),
            crewMembers: $this->crewMemberDataBuilder->fromSerialized($data['crewMembers'] ?? null),
            waypoints: $this->waypointDataBuilder->fromSerialized($data['waypoints'] ?? null),
        );
    }

    private function generalDeclaration(mixed $value): GeneralDeclarationData
    {
        return new GeneralDeclarationData(
            sectionPresent: is_array($value) && ($value['sectionPresent'] ?? false) === true,
        );
    }

    private function releaseAuthorization(mixed $value): ReleaseAuthorizationData
    {
        $operationsSpecification = is_array($value)
            ? OperationsSpecification::tryFrom($this->nullableString($value['operationsSpecification'] ?? null) ?? '')
            : null;

        return new ReleaseAuthorizationData(
            operationsSpecification: $operationsSpecification ?? OperationsSpecification::Unknown,
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
            'costIndex' => $this->fuelPlanFieldNormalizer->costIndex($data['costIndex'] ?? null),
            'ramp' => $this->fuelQuantity($data['ramp'] ?? null),
            'taxi' => $this->fuelQuantity($data['taxi'] ?? null),
            'takeoff' => $this->fuelQuantity($data['takeoff'] ?? null),
            'trip' => $this->fuelQuantity($data['trip'] ?? null),
            'contingency' => $this->fuelQuantity($data['contingency'] ?? null),
            'alternate' => $this->fuelQuantity($data['alternate'] ?? null),
            'finalReserve' => $this->fuelQuantity($data['finalReserve'] ?? null),
            'estimatedLanding' => $this->fuelQuantity($data['estimatedLanding'] ?? null),
        ];

        if (array_filter($quantities, static fn (mixed $value): bool => $value !== null) === []) {
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

    private function airportCode(mixed $value): ?AirportCode
    {
        return is_string($value) && $value !== '' ? new AirportCode($value) : null;
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
}
