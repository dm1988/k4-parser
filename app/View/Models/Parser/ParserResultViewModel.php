<?php

namespace App\View\Models\Parser;

use App\DTOs\AirportData;
use App\DTOs\DutyEvent;
use App\DTOs\Flight;
use App\DTOs\ParserResultData;
use App\Enums\MetadataKey;
use App\Mappers\DutyEventMapper;
use App\Mappers\FlightMapper;
use App\Services\AirportLookupClient;

readonly class ParserResultViewModel
{
    /**
     * @param  list<ParserEventViewModel|Flight|DutyEvent>  $events
     * @param  list<string>  $filters
     */
    public function __construct(
        public ?string $errorMessage,
        public string $sourceLabel,
        public string $tripNumber,
        public int $eventCount,
        public array $events,
        public ?string $parseKey,
        public ?string $exportUrl,
        public string $rawJson,
    ) {}

    public static function fromData(ParserResultData $result): self
    {
        $filters = array_values(array_filter(
            $result->filters,
            fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
        $parseKey = $result->parseKey;
        $eventViewModels = [];
        $airportLookupClient = app(AirportLookupClient::class);

        foreach (($result->parsed['calendar_events'] ?? []) as $event) {
            if ($parseKey === null) {
                continue;
            }

            if ($event instanceof Flight) {
                $event = self::enrichFlightAirports($event, $airportLookupClient);
                $downloadId = (string) ($event->downloadId ?? '');
                $eventViewModels[] = $downloadId === ''
                    ? $event
                    : $event->withDownloadUrl(route('parse.export.event', ['eventId' => $downloadId, 'parse_key' => $parseKey]));

                continue;
            }

            if ($event instanceof DutyEvent) {
                $downloadId = (string) ($event->downloadId ?? '');
                $eventViewModels[] = $downloadId === ''
                    ? $event
                    : $event->withDownloadUrl(route('parse.export.event', ['eventId' => $downloadId, 'parse_key' => $parseKey]));

                continue;
            }

            if (! is_array($event)) {
                continue;
            }

            $flight = app(FlightMapper::class)->fromCalendarEvent($event, $event[MetadataKey::DownloadId->value] ?? null);

            if ($flight !== null) {
                $flight = self::enrichFlightAirports($flight, $airportLookupClient);
                $downloadId = (string) ($event[MetadataKey::DownloadId->value] ?? '');
                $eventViewModels[] = $downloadId === ''
                    ? $flight
                    : $flight->withDownloadUrl(route('parse.export.event', ['eventId' => $downloadId, 'parse_key' => $parseKey]));

                continue;
            }

            $duty = app(DutyEventMapper::class)->fromCalendarEvent($event, $event[MetadataKey::DownloadId->value] ?? null);

            if ($duty !== null) {
                $downloadId = (string) ($event[MetadataKey::DownloadId->value] ?? '');
                $eventViewModels[] = $downloadId === ''
                    ? $duty
                    : $duty->withDownloadUrl(route('parse.export.event', ['eventId' => $downloadId, 'parse_key' => $parseKey]));

                continue;
            }

            $eventViewModels[] = ParserEventViewModel::fromArray($event, $parseKey);
        }

        return new self(
            errorMessage: $result->error,
            sourceLabel: ucfirst($result->source),
            tripNumber: (string) ($result->parsed['trip']['trip_number'] ?? 'Pending'),
            eventCount: count($eventViewModels),
            events: $eventViewModels,
            parseKey: $parseKey,
            exportUrl: $eventViewModels === [] || $result->parseKey === null
                ? null
                : route('parse.export', ['event_types' => $filters, 'parse_key' => $result->parseKey]),
            rawJson: json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
        );
    }

    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }

    private static function enrichFlightAirports(Flight $flight, AirportLookupClient $airportLookupClient): Flight
    {
        $metadata = $flight->metadata;

        $metadata = self::mergeAirportMetadata(
            $metadata,
            'origin',
            self::lookupAirportByIata($airportLookupClient, $flight->origin),
        );

        $metadata = self::mergeAirportMetadata(
            $metadata,
            'destination',
            self::lookupAirportByIata($airportLookupClient, $flight->destination),
        );

        if ($metadata === $flight->metadata) {
            return $flight;
        }

        return $flight->withMetadata($metadata);
    }

    private static function lookupAirportByIata(AirportLookupClient $airportLookupClient, ?string $iata): ?AirportData
    {
        if (! is_string($iata) || trim($iata) === '') {
            return null;
        }

        return $airportLookupClient->lookupByIata($iata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private static function mergeAirportMetadata(array $metadata, string $prefix, ?AirportData $airport): array
    {
        if ($airport === null) {
            return $metadata;
        }

        return [
            ...self::airportMetadata($prefix, $airport),
            ...$metadata,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function airportMetadata(string $prefix, AirportData $airport): array
    {
        $metadata = [
            "{$prefix}_iata" => $airport->iata,
            "{$prefix}_icao" => $airport->icao,
            "{$prefix}_name" => $airport->name,
            "{$prefix}_city" => $airport->city,
            "{$prefix}_country" => $airport->country,
        ];

        if ($airport->state !== null) {
            $metadata["{$prefix}_state"] = $airport->state;
        }

        return $metadata;
    }
}
