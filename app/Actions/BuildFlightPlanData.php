<?php

namespace App\Actions;

use App\DTOs\CrewMemberData;
use App\DTOs\FlightIdentityData;
use App\DTOs\FlightPlanData;
use App\DTOs\FuelPlanData;
use App\DTOs\MaintenanceItemData;
use App\DTOs\MaintenanceLogData;
use App\DTOs\ParsedFlightPlanData;
use App\DTOs\RouteData;
use App\DTOs\ScheduleData;
use App\Enums\EtopsApplicability;
use App\Enums\MaintenanceItemType;
use App\ValueObjects\AirportCode;
use App\ValueObjects\FlightTime;
use App\ValueObjects\FuelQuantity;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Str;

class BuildFlightPlanData
{
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
