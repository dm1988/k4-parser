<?php

namespace App\View\Models\Parser;

use App\DTOs\DutyEvent;
use App\DTOs\Flight;
use App\Mappers\DutyEventMapper;
use App\Mappers\FlightMapper;

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

    public static function fromArray(array $result): self
    {
        $filters = array_values(array_filter(
            $result['filters'] ?? [],
            fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
        $parseKey = is_string($result['parse_key'] ?? null) ? $result['parse_key'] : null;
        $eventViewModels = [];

        foreach (($result['parsed']['calendar_events'] ?? []) as $event) {
            if ($parseKey === null) {
                continue;
            }

            if ($event instanceof Flight) {
                $eventViewModels[] = $event;
                continue;
            }

            if (! is_array($event)) {
                continue;
            }

            $flight = app(FlightMapper::class)->fromCalendarEvent($event, $event['download_id'] ?? null);

            if ($flight !== null) {
                $downloadId = (string) ($event['download_id'] ?? '');
                $eventViewModels[] = $downloadId === ''
                    ? $flight
                    : $flight->withDownloadUrl(route('parse.export.event', ['eventId' => $downloadId, 'parse_key' => $parseKey]));
                continue;
            }

            $duty = app(DutyEventMapper::class)->fromCalendarEvent($event, $event['download_id'] ?? null);

            if ($duty !== null) {
                $downloadId = (string) ($event['download_id'] ?? '');
                $eventViewModels[] = $downloadId === ''
                    ? $duty
                    : $duty->withDownloadUrl(route('parse.export.event', ['eventId' => $downloadId, 'parse_key' => $parseKey]));
                continue;
            }

            $eventViewModels[] = ParserEventViewModel::fromArray($event, $parseKey);
        }

        return new self(
            errorMessage: is_string($result['error'] ?? null) ? $result['error'] : null,
            sourceLabel: ucfirst((string) ($result['source'] ?? 'text')),
            tripNumber: (string) ($result['parsed']['trip']['trip_number'] ?? 'Pending'),
            eventCount: count($eventViewModels),
            events: $eventViewModels,
            parseKey: $parseKey,
            exportUrl: $eventViewModels === [] || ! is_string($result['parse_key'] ?? null)
                ? null
                : route('parse.export', ['event_types' => $filters, 'parse_key' => $result['parse_key']]),
            rawJson: json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
        );
    }

    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }
}
