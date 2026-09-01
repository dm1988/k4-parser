<?php

namespace App\Actions;

use App\DTOs\FlightIdentityData;
use App\DTOs\FlightInitData;
use App\DTOs\FlightPlanData;
use App\DTOs\FuelPlanData;
use App\DTOs\GeneralDeclarationData;
use App\DTOs\ParsedFlightPlanData;
use App\DTOs\ReleaseAuthorizationData;
use App\DTOs\RouteData;
use App\DTOs\ScheduleData;
use App\DTOs\SlotTimeData;
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
use App\ValueObjects\FlightTime;
use App\ValueObjects\FuelQuantity;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

class BuildFlightPlanData
{
    public function __construct(
        private readonly FlightInitFieldNormalizer $flightInitFieldNormalizer,
        private readonly FuelPlanFieldNormalizer $fuelPlanFieldNormalizer,
        private readonly WeightBalanceDataBuilder $weightBalanceDataBuilder,
        private readonly EtopsDataBuilder $etopsDataBuilder,
        private readonly MaintenanceLogDataBuilder $maintenanceLogDataBuilder,
        private readonly WeatherDataBuilder $weatherDataBuilder,
        private readonly CrewMemberDataBuilder $crewMemberDataBuilder,
        private readonly WaypointDataBuilder $waypointDataBuilder,
        private readonly TakeoffLandingReportDataBuilder $takeoffLandingReportDataBuilder,
    ) {}

    public function handle(ParsedFlightPlanData $parsed): FlightPlanData
    {
        $fuelPlan = $this->fuelPlan($parsed);

        return new FlightPlanData(
            identity: new FlightIdentityData(
                flightNumber: $parsed->identity['flight_number'] ?? null,
                tripNumber: $parsed->identity['trip_number'] ?? null,
                recallNumber: $parsed->identity['recall_number'] ?? null,
                aircraftType: $parsed->identity['aircraft_type'] ?? null,
                tailNumber: $parsed->identity['tail_number'] ?? null,
                flightDate: $this->flightDate($parsed->identity['flight_date'] ?? null),
                releaseRevision: $parsed->identity['release_revision'] ?? null,
            ),
            schedule: new ScheduleData(
                etdUtc: $this->utcTime($parsed->schedule['etd_utc'] ?? null),
                etaUtc: $this->utcTime($parsed->schedule['eta_utc'] ?? null),
                blockDuration: $parsed->schedule['block_duration'] ?? null,
                reportTimeUtc: $this->utcTime($parsed->schedule['report_time_utc'] ?? null),
                dutyEndUtc: $this->utcTime($parsed->schedule['duty_end_utc'] ?? null),
                slotSourceText: $parsed->schedule['slot_source_text'] ?? null,
                slots: $this->slots($parsed->schedule['slots'] ?? []),
                slotTimesUtc: array_map(
                    fn (?string $time): ?string => $this->utcTime($time),
                    $parsed->schedule['slot_times_utc'] ?? [],
                ),
            ),
            route: new RouteData(
                departure: new AirportCode($parsed->route['departure'] ?? ''),
                destination: new AirportCode($parsed->route['destination'] ?? ''),
                alternate: $this->airportCode($parsed->route['alternate'] ?? null),
                route: $parsed->route['route'] ?? null,
                departureRunway: $parsed->route['departure_runway'] ?? null,
                arrivalRunway: $parsed->route['arrival_runway'] ?? null,
                departureSid: $parsed->route['departure_sid'] ?? null,
                arrivalStar: $parsed->route['arrival_star'] ?? null,
                distanceNauticalMiles: $parsed->route['distance_nautical_miles'] ?? null,
            ),
            fuelPlan: $fuelPlan,
            maintenanceLog: $this->maintenanceLogDataBuilder->fromExtracted($parsed->maintenance),
            takeoffLandingReport: $this->takeoffLandingReportDataBuilder->fromExtracted($parsed->takeoffLandingReport),
            flightInit: $this->flightInit($parsed),
            etops: $this->etopsDataBuilder->fromExtracted($parsed->etops),
            weather: $this->weatherDataBuilder->fromExtracted($parsed->weather),
            weightBalance: $this->weightBalanceDataBuilder->build($parsed->weightBalance, $fuelPlan, $parsed->fuel),
            generalDeclaration: new GeneralDeclarationData(
                sectionPresent: ($parsed->generalDeclaration['section_present'] ?? false) === true,
            ),
            releaseAuthorization: new ReleaseAuthorizationData(
                operationsSpecification: OperationsSpecification::tryFrom(
                    is_string($parsed->releaseAuthorization['operations_specification'] ?? null)
                        ? $parsed->releaseAuthorization['operations_specification']
                        : '',
                ) ?? OperationsSpecification::Unknown,
            ),
            crewMembers: $this->crewMemberDataBuilder->fromExtracted($parsed->crewMembers),
            waypoints: $this->waypointDataBuilder->fromExtracted($parsed->waypoints, $parsed->fuel),
        );
    }

    private function flightDate(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (InvalidFormatException) {
            return null;
        }

        if ($date === null || $date->toDateString() !== $value) {
            return null;
        }

        return $date;
    }

    private function utcTime(?string $value): ?string
    {
        return $value === null ? null : FlightTime::utc($value)->toIso8601String();
    }

    /**
     * @param  list<array{direction: string, airport: string, instant_utc: string, source_time: string, tolerance_minutes: ?int}>  $slots
     * @return list<SlotTimeData>
     */
    private function slots(array $slots): array
    {
        $normalized = [];

        foreach ($slots as $slot) {
            $slotData = SlotTimeData::fromArray($slot);

            if ($slotData !== null) {
                $normalized[] = $slotData;
            }
        }

        return $normalized;
    }

    private function airportCode(?string $value): ?AirportCode
    {
        return $value === null ? null : new AirportCode($value);
    }

    private function fuelPlan(ParsedFlightPlanData $parsed): ?FuelPlanData
    {
        $fuel = $parsed->fuel;

        if (array_filter($fuel, static fn (mixed $value): bool => $value !== null) === []) {
            return null;
        }

        return new FuelPlanData(
            costIndex: $this->fuelPlanFieldNormalizer->costIndex($fuel['cost_index'] ?? null),
            ramp: $this->fuelQuantity($fuel['ramp'] ?? null),
            taxi: $this->fuelQuantity($fuel['taxi'] ?? null),
            takeoff: $this->fuelQuantity($fuel['takeoff'] ?? null),
            trip: $this->fuelQuantity($fuel['trip'] ?? null),
            contingency: $this->fuelQuantity($fuel['contingency'] ?? null),
            alternate: $this->fuelQuantity($fuel['alternate'] ?? null),
            finalReserve: $this->fuelQuantity($fuel['final_reserve'] ?? null),
            estimatedLanding: $this->fuelQuantity($fuel['estimated_landing'] ?? null),
        );
    }

    /** @param array{amount: float, unit: string}|null $fuel */
    private function fuelQuantity(?array $fuel): ?FuelQuantity
    {
        return $fuel === null ? null : new FuelQuantity($fuel['amount'], $fuel['unit']);
    }

    private function flightInit(ParsedFlightPlanData $parsed): ?FlightInitData
    {
        $flightInit = $parsed->flightInit;

        if (($flightInit['section_present'] ?? false) !== true) {
            return null;
        }

        return new FlightInitData(
            sectionPresent: true,
            acarsInitDate: $this->flightInitFieldNormalizer->acarsInitDate($flightInit['acars_init_date'] ?? null),
            filedInitialAltitude: $this->flightInitFieldNormalizer->filedInitialAltitude($flightInit['filed_initial_altitude'] ?? null),
            fmsInitialAltitude: $this->flightInitFieldNormalizer->fmsInitialAltitude($flightInit['fms_initial_altitude'] ?? null),
        );
    }
}
