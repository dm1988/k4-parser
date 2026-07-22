<?php

namespace App\Services\Schedule;

use App\DTOs\AirportData;
use App\DTOs\AirportResolution;
use App\DTOs\Flight;
use App\Enums\MetadataKey;
use App\Mappers\FlightMapper;

class AirportEnrichmentService
{
    public function __construct(
        private readonly AirportResolver $airportResolver,
        private readonly FlightMapper $flightMapper,
    ) {}

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public function enrich(array $parsed): array
    {
        $events = is_array($parsed['calendar_events'] ?? null) ? $parsed['calendar_events'] : [];
        $flights = array_map(fn (mixed $event): ?Flight => $this->flightFromEvent($event), $events);
        $codes = [];

        foreach ($flights as $flight) {
            if ($flight === null) {
                continue;
            }

            if ($flight->origin !== null) {
                $codes[] = $flight->origin;
            }

            if ($flight->destination !== null) {
                $codes[] = $flight->destination;
            }
        }

        $resolutions = $this->airportResolver->resolveMany($codes);

        foreach ($events as $index => $event) {
            $flight = $flights[$index] ?? null;

            if ($flight === null) {
                continue;
            }

            $metadata = $flight->metadata;
            $metadata = $this->mergeResolution($metadata, 'origin', $flight->origin, $resolutions);
            $metadata = $this->mergeResolution($metadata, 'destination', $flight->destination, $resolutions);

            if ($event instanceof Flight) {
                $events[$index] = $event->withMetadata($metadata);
            } elseif (is_array($event)) {
                $events[$index]['metadata'] = $metadata;
            }
        }

        $parsed['calendar_events'] = $events;

        return $parsed;
    }

    private function flightFromEvent(mixed $event): ?Flight
    {
        if ($event instanceof Flight) {
            return $event;
        }

        if (! is_array($event)) {
            return null;
        }

        return $this->flightMapper->fromCalendarEvent(
            $event,
            is_scalar($event[MetadataKey::DownloadId->value] ?? null)
                ? (string) $event[MetadataKey::DownloadId->value]
                : null,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, AirportResolution>  $resolutions
     * @return array<string, mixed>
     */
    private function mergeResolution(array $metadata, string $prefix, ?string $code, array $resolutions): array
    {
        $normalizedCode = strtoupper(trim((string) $code));
        $resolution = $resolutions[$normalizedCode] ?? null;

        if ($resolution === null) {
            return $metadata;
        }

        $metadata["{$prefix}_airport_status"] = $resolution->status->value;

        return $resolution->airport === null
            ? $metadata
            : [...$this->airportMetadata($prefix, $resolution->airport), ...$metadata];
    }

    /** @return array<string, string> */
    private function airportMetadata(string $prefix, AirportData $airport): array
    {
        return array_filter([
            "{$prefix}_iata" => $airport->iata,
            "{$prefix}_icao" => $airport->icao,
            "{$prefix}_name" => $airport->name,
            "{$prefix}_city" => $airport->city,
            "{$prefix}_state" => $airport->state,
            "{$prefix}_country" => $airport->country,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
