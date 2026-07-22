<?php

namespace App\View\Models\Parser;

use App\DTOs\DutyEvent;
use App\DTOs\Flight;
use App\DTOs\ParserResultData;
use App\Enums\MetadataKey;
use App\Mappers\DutyEventMapper;
use App\Mappers\FlightMapper;

readonly class ParserResultViewModel
{
    /**
     * @param  list<ParserEventViewModel|Flight|DutyEvent>  $events
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
            fn (string $value): bool => $value !== '',
        ));
        $parseKey = $result->parseKey;
        $eventViewModels = [];

        foreach (($result->parsed['calendar_events'] ?? []) as $event) {
            if ($parseKey === null) {
                continue;
            }

            if ($event instanceof Flight) {
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
}
