<?php

namespace App\Mappers;

use App\DTOs\AeroDataBoxFlightData;
use Carbon\CarbonImmutable;
use Throwable;

class AeroDataBoxFlightMapper
{
    /** @param array<string, mixed> $flight */
    public function map(array $flight, string $fallbackTailNumber): ?AeroDataBoxFlightData
    {
        $scheduledDeparture = $this->date(data_get($flight, 'departure.scheduledTime.utc'));
        $start = $this->movementTime($flight, 'departure');
        $end = $this->movementTime($flight, 'arrival');
        $origin = $this->airportCode($flight, 'departure');
        $destination = $this->airportCode($flight, 'arrival');
        $tailNumber = strtoupper(trim((string) data_get($flight, 'aircraft.reg', $fallbackTailNumber)));
        $flightNumber = trim((string) ($flight['number'] ?? $flight['callSign'] ?? ''));
        $providerFlightId = $this->providerFlightId($flight);

        if (($providerFlightId === null && $scheduledDeparture === null)
            || $start === null || $end === null
            || $origin === null || $destination === null || $tailNumber === '' || $flightNumber === '') {
            return null;
        }

        $reportedEnd = $end;
        $hasTimelineAnomaly = $end->lessThanOrEqualTo($start);

        if ($hasTimelineAnomaly) {
            $end = $start->addMinute();
        }

        $rawStatus = (string) ($flight['status'] ?? 'Unknown');
        [$status, $badgeColor] = $this->status($rawStatus);
        $externalId = $providerFlightId !== null
            ? hash('sha256', 'aerodatabox|'.$providerFlightId)
            : hash('sha256', implode('|', [
                $tailNumber,
                strtoupper($flightNumber),
                $scheduledDeparture->toIso8601String(),
                $origin,
                $destination,
            ]));

        return new AeroDataBoxFlightData(
            externalId: $externalId,
            tailNumber: $tailNumber,
            flightNumber: $flightNumber,
            origin: $origin,
            destination: $destination,
            start: $start,
            end: $end,
            status: $status,
            badgeColor: $badgeColor,
            metadata: [
                'source' => 'aerodatabox',
                'provider_flight_id' => $providerFlightId,
                'timeline_anomaly' => $hasTimelineAnomaly,
                'reported_end_utc' => $hasTimelineAnomaly ? $reportedEnd->toIso8601String() : null,
                'raw_status' => $rawStatus,
                'last_updated_utc' => $flight['lastUpdatedUtc'] ?? null,
                'call_sign' => $flight['callSign'] ?? null,
                'is_cargo' => $flight['isCargo'] ?? null,
                'scheduled_departure_utc' => $scheduledDeparture?->toIso8601String(),
                'aircraft' => $flight['aircraft'] ?? null,
                'airline' => $flight['airline'] ?? null,
                'departure' => $flight['departure'] ?? null,
                'arrival' => $flight['arrival'] ?? null,
                'raw' => $flight,
            ],
        );
    }

    /** @param array<string, mixed> $flight */
    private function providerFlightId(array $flight): ?string
    {
        $id = $flight['id'] ?? $flight['flightId'] ?? null;

        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        $id = trim((string) $id);

        return $id === '' ? null : $id;
    }

    /** @param array<string, mixed> $flight */
    private function movementTime(array $flight, string $movement): ?CarbonImmutable
    {
        foreach (['actualTime', 'revisedTime', 'predictedTime', 'scheduledTime'] as $field) {
            $date = $this->date(data_get($flight, "{$movement}.{$field}.utc"));

            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $flight */
    private function airportCode(array $flight, string $movement): ?string
    {
        $code = data_get($flight, "{$movement}.airport.iata")
            ?? data_get($flight, "{$movement}.airport.icao");
        $code = strtoupper(trim((string) $code));

        return $code === '' ? null : $code;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{string, string} */
    private function status(string $status): array
    {
        return match (strtolower($status)) {
            'arrived' => ['Arrived', 'green'],
            'canceled', 'cancelled', 'canceleduncertain' => ['Cancelled', 'red'],
            'enroute', 'departed', 'approaching', 'delayed' => ['En Route', 'blue'],
            'diverted' => ['Diverted', 'amber'],
            default => ['Scheduled', 'gray'],
        };
    }
}
