<?php

namespace App\Actions;

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
use App\DTOs\ParsedFlightPlanData;
use App\DTOs\RouteData;
use App\DTOs\ScheduleData;
use App\Enums\EtopsApplicability;
use App\Enums\MaintenanceItemType;
use App\Services\FlightPlan\FlightInitFieldNormalizer;
use App\ValueObjects\AirportCode;
use App\ValueObjects\FlightTime;
use App\ValueObjects\FuelQuantity;
use App\ValueObjects\WeightQuantity;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BuildFlightPlanData
{
    public function __construct(
        private readonly FlightInitFieldNormalizer $flightInitFieldNormalizer = new FlightInitFieldNormalizer,
    ) {}

    public function handle(ParsedFlightPlanData $parsed): FlightPlanData
    {
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
            fuelPlan: $this->fuelPlan($parsed),
            maintenanceLog: $this->maintenanceLog($parsed),
            envelope: $this->envelope($parsed),
            flightInit: $this->flightInit($parsed),
            etops: $this->etops($parsed),
            crewMembers: $this->crewMembers($parsed->crewMembers),
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

    private function airportCode(?string $value): ?AirportCode
    {
        return $value === null ? null : new AirportCode($value);
    }

    private function fuelPlan(ParsedFlightPlanData $parsed): ?FuelPlanData
    {
        $fuel = $parsed->fuel;

        if (array_filter($fuel, static fn (?array $value): bool => $value !== null) === []) {
            return null;
        }

        return new FuelPlanData(
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

    private function maintenanceLog(ParsedFlightPlanData $parsed): MaintenanceLogData
    {
        $maintenance = $parsed->maintenance;
        $applicability = is_string($maintenance['etops_applicability'] ?? null)
            ? EtopsApplicability::tryFrom($maintenance['etops_applicability'])
            : null;

        return new MaintenanceLogData(
            sectionPresent: ($maintenance['section_present'] ?? false) === true,
            etopsApplicability: $applicability ?? EtopsApplicability::Unknown,
            items: $this->maintenanceItems($maintenance['items'] ?? null),
        );
    }

    private function envelope(ParsedFlightPlanData $parsed): ?EnvelopeData
    {
        $envelope = $parsed->envelope;

        if (($envelope['section_present'] ?? false) !== true) {
            return null;
        }

        return new EnvelopeData(
            sectionPresent: true,
            sourceType: $this->nullableString($envelope['source_type'] ?? null) ?? 'takeoff_landing_report',
            reportReference: $this->nullableString($envelope['report_reference'] ?? null),
            airport: $this->nullableString($envelope['airport'] ?? null),
            plannedRunway: $this->nullableString($envelope['planned_runway'] ?? null),
            outsideAirTemperatureCelsius: $this->nullableFloat($envelope['outside_air_temperature_celsius'] ?? null),
            wind: $this->nullableString($envelope['wind'] ?? null),
            qnhInchesMercury: $this->nullableFloat($envelope['qnh_inches_mercury'] ?? null),
            qnhHectopascals: $this->nullableInteger($envelope['qnh_hectopascals'] ?? null),
            maximumRunwayTakeoffWeight: $this->weightQuantity($envelope['maximum_runway_takeoff_weight'] ?? null),
            flapSetting: $this->nullableString($envelope['flap_setting'] ?? null),
            antiIce: is_bool($envelope['anti_ice'] ?? null) ? $envelope['anti_ice'] : null,
            v1Knots: $this->nullableInteger($envelope['v1_knots'] ?? null),
            rotateKnots: $this->nullableInteger($envelope['rotate_knots'] ?? null),
            v2Knots: $this->nullableInteger($envelope['v2_knots'] ?? null),
            plannedTakeoffWeight: $this->weightQuantity($envelope['planned_takeoff_weight'] ?? null),
            maximumFieldTakeoffWeight: $this->weightQuantity($envelope['maximum_field_takeoff_weight'] ?? null),
            sourceWarnings: $this->strings($envelope['source_warnings'] ?? null),
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

    private function flightInit(ParsedFlightPlanData $parsed): ?FlightInitData
    {
        $flightInit = $parsed->flightInit;

        if (($flightInit['section_present'] ?? false) !== true) {
            return null;
        }

        return new FlightInitData(
            sectionPresent: true,
            acarsInitDate: $this->flightInitFieldNormalizer->acarsInitDate($flightInit['acars_init_date'] ?? null),
        );
    }

    private function etops(ParsedFlightPlanData $parsed): ?EtopsData
    {
        $entryPoint = $this->etopsPoint('EENT', $parsed->etops['eent_coordinates'] ?? null, 0);
        $equalTimePoints = [];
        $scenarios = [];
        $etpValues = $parsed->etops['etps'] ?? [];
        $sequence = 1;

        foreach ($etpValues as $value) {
            $label = $this->nullableString($value['label']);
            $coordinate = $this->etopsCoordinate($value['coordinates']);
            $airports = explode('-', $value['airports']);
            $scenario = $this->nullableString($value['scenario']);

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

        $exitPoint = $this->etopsPoint(
            'EEXP',
            $parsed->etops['eexp_coordinates'] ?? null,
            $sequence,
        );

        if ($entryPoint === null && $equalTimePoints === [] && $exitPoint === null) {
            return null;
        }

        $applicability = is_string($parsed->maintenance['etops_applicability'] ?? null)
            ? EtopsApplicability::tryFrom($parsed->maintenance['etops_applicability'])
            : null;

        return new EtopsData(
            sectionPresent: true,
            applicability: $applicability ?? EtopsApplicability::Unknown,
            entryPoint: $entryPoint,
            exitPoint: $exitPoint,
            equalTimePoints: $equalTimePoints,
            scenarios: $scenarios,
        );
    }

    private function etopsPoint(string $label, mixed $coordinates, int $sequence): ?EtopsPointData
    {
        $coordinate = $this->etopsCoordinate($coordinates);

        return $coordinate === null ? null : new EtopsPointData($label, $coordinate, $sequence);
    }

    private function etopsCoordinate(mixed $value): ?EtopsCoordinateData
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
                ? MaintenanceItemType::tryFrom(Str::upper($item['type']))
                : null;
            $number = $this->nullableString($item['number'] ?? null);
            $description = $this->nullableString($item['description'] ?? null);

            if ($type === null || $number === null || $description === null) {
                continue;
            }

            $normalized[] = new MaintenanceItemData(
                type: $type,
                number: Str::upper($number),
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
                employeeNumber: $this->flightInitFieldNormalizer->employeeNumber($member['employee_number'] ?? null),
            );
        }

        return $normalized;
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
