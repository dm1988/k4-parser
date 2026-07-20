<?php

namespace App\Mappers;

use App\DTOs\Flight;
use App\Enums\MetadataKey;
use App\Enums\ParserEventType;
use Carbon\CarbonImmutable;

final class FlightMapper
{
    public function fromCalendarEvent(array $event, ?string $downloadId = null): ?Flight
    {
        $eventType = ParserEventType::fromEvent($event);

        if (! $eventType->isFlightLike()) {
            return null;
        }

        $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        $start = $this->nullableString($event, 'start');
        $end = $this->nullableString($event, 'end');
        [$scheduleLabel, $durationLabel] = $this->buildTimeLabels(
            $start,
            $end,
            $this->nullableString($event, 'scheduleLabel', 'schedule_label'),
            $this->nullableString($event, 'durationLabel', 'duration_label') ?? $this->nullableString($metadata, MetadataKey::BlockTime->value),
        );

        return new Flight(
            title: (string) ($event['title'] ?? 'Untitled event'),
            type: $eventType->value,
            typeLabel: (string) ($event['typeLabel'] ?? $event['type_label'] ?? $eventType->label()),
            typeDescription: (string) ($event['typeDescription'] ?? $event['type_description'] ?? $eventType->description()),
            typeIcon: (string) ($event['typeIcon'] ?? $event['type_icon'] ?? $eventType->icon()),
            scheduleLabel: $scheduleLabel,
            durationLabel: $durationLabel,
            tailNumber: $this->normalizeTailNumber(
                $this->nullableString($metadata, MetadataKey::TailNumber->value) ?? $this->nullableString($metadata, MetadataKey::Aircraft->value)
            ),
            isDeadhead: (bool) ($metadata[MetadataKey::Deadhead->value] ?? $event['is_deadhead'] ?? false),
            badgeColor: (string) ($event['badgeColor'] ?? $event['badge_color'] ?? $eventType->badgeColor()),
            downloadUrl: (string) ($metadata[MetadataKey::FlightawareUrl->value] ?? $metadata[MetadataKey::DownloadUrl->value] ?? $event['download_url'] ?? ''),
            downloadId: $downloadId ?? $this->nullableString($event, MetadataKey::DownloadId->value),
            flightNumber: $this->nullableString($metadata, MetadataKey::FlightNumber->value),
            position: $this->nullableString($metadata, MetadataKey::Position->value),
            aircraft: $this->nullableString($metadata, MetadataKey::Aircraft->value),
            blockTime: $this->nullableString($metadata, MetadataKey::BlockTime->value),
            tripId: $this->nullableString($metadata, MetadataKey::TripId->value),
            crewCount: $this->nullableInt($metadata, MetadataKey::CrewCount->value),
            operatingCrewCount: $this->nullableInt($metadata, MetadataKey::OperatingCrewCount->value),
            deadheadingCrewCount: $this->nullableInt($metadata, MetadataKey::DeadheadingCrewCount->value),
            dutyStation: $this->nullableString($metadata, MetadataKey::DutyStation->value),
            legLocalStart: $this->nullableString($metadata, MetadataKey::LegLocalStart->value),
            legLocalEnd: $this->nullableString($metadata, MetadataKey::LegLocalEnd->value),
            dutyLocalStart: $this->nullableString($metadata, MetadataKey::DutyLocalStart->value),
            dutyLocalEnd: $this->nullableString($metadata, MetadataKey::DutyLocalEnd->value),
            start: $start,
            end: $end,
            timezone: $this->nullableString($event, 'timezone'),
            origin: $this->nullableString($metadata, MetadataKey::Origin->value) ?? $this->nullableString($metadata, 'station'),
            destination: $this->nullableString($metadata, MetadataKey::Destination->value),
            rawLines: $this->stringList($metadata[MetadataKey::RawLines->value] ?? []),
            dutyRawLines: $this->stringList($metadata[MetadataKey::DutyRawLines->value] ?? []),
            metadata: $metadata,
        );
    }

    public function withDownloadId(Flight $flight, string $downloadId): Flight
    {
        return $flight->withDownloadId($downloadId);
    }

    public function toCalendarEvent(Flight $flight): array
    {
        $metadata = $flight->metadata;
        $metadata[MetadataKey::FlightNumber->value] = $flight->flightNumber;
        $metadata[MetadataKey::Origin->value] = $flight->origin;
        $metadata[MetadataKey::Destination->value] = $flight->destination;
        $metadata[MetadataKey::Position->value] = $flight->position;
        $metadata[MetadataKey::Aircraft->value] = $flight->aircraft;
        $metadata[MetadataKey::TailNumber->value] = $flight->tailNumber;
        $metadata[MetadataKey::BlockTime->value] = $flight->blockTime;
        $metadata[MetadataKey::TripId->value] = $flight->tripId;
        $metadata[MetadataKey::CrewCount->value] = $flight->crewCount;
        $metadata[MetadataKey::OperatingCrewCount->value] = $flight->operatingCrewCount;
        $metadata[MetadataKey::DeadheadingCrewCount->value] = $flight->deadheadingCrewCount;
        $metadata[MetadataKey::DutyStation->value] = $flight->dutyStation;
        $metadata[MetadataKey::LegLocalStart->value] = $flight->legLocalStart;
        $metadata[MetadataKey::LegLocalEnd->value] = $flight->legLocalEnd;
        $metadata[MetadataKey::DutyLocalStart->value] = $flight->dutyLocalStart;
        $metadata[MetadataKey::DutyLocalEnd->value] = $flight->dutyLocalEnd;
        $metadata[MetadataKey::Deadhead->value] = $flight->isDeadhead;
        $metadata[MetadataKey::FlightawareUrl->value] = $flight->downloadUrl !== '' ? $flight->downloadUrl : ($metadata[MetadataKey::FlightawareUrl->value] ?? null);
        $metadata[MetadataKey::RawLines->value] = $flight->rawLines;
        $metadata[MetadataKey::DutyRawLines->value] = $flight->dutyRawLines;

        return [
            'title' => $flight->title,
            'type' => $flight->type,
            'start' => $flight->start,
            'end' => $flight->end,
            'timezone' => $flight->timezone,
            'download_id' => $flight->downloadId,
            'metadata' => array_filter(
                $metadata,
                static fn (mixed $value): bool => $value !== null && $value !== '',
            ),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function buildTimeLabels(?string $startValue, ?string $endValue, ?string $scheduleLabel, ?string $durationLabel): array
    {
        if ($startValue === null || $endValue === null) {
            return [$scheduleLabel ?? '', $durationLabel ?? ''];
        }

        $start = CarbonImmutable::parse($startValue);
        $end = CarbonImmutable::parse($endValue);
        $sameDay = $start->isSameDay($end);
        $durationMinutes = (int) $start->diffInMinutes($end);
        $hours = intdiv($durationMinutes, 60);
        $minutes = $durationMinutes % 60;

        return [
            $scheduleLabel ?? (
                $sameDay
                    ? $start->format('M j • g:i A').' - '.$end->format('g:i A')
                    : $start->format('M j, g:i A').' -> '.$end->format('M j, g:i A')
            ),
            $durationLabel ?? ($hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m"),
        ];
    }

    private function nullableString(array $data, string $primaryKey, ?string $secondaryKey = null): ?string
    {
        $value = $data[$primaryKey] ?? ($secondaryKey !== null ? ($data[$secondaryKey] ?? null) : null);

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values($normalized);
    }

    private function normalizeTailNumber(?string $tailNumber): ?string
    {
        return $tailNumber === null ? null : strtoupper($tailNumber);
    }
}
