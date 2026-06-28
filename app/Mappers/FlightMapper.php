<?php

namespace App\Mappers;

use App\DTOs\Flight;
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
            $this->nullableString($event, 'durationLabel', 'duration_label') ?? $this->nullableString($metadata, 'block_time'),
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
                $this->nullableString($metadata, 'tail_number') ?? $this->nullableString($metadata, 'aircraft')
            ),
            isDeadhead: (bool) ($metadata['deadhead'] ?? $event['is_deadhead'] ?? false),
            badgeColor: (string) ($event['badgeColor'] ?? $event['badge_color'] ?? $eventType->badgeColor()),
            downloadUrl: (string) ($metadata['flightaware_url'] ?? $metadata['download_url'] ?? $event['download_url'] ?? ''),
            downloadId: $downloadId ?? $this->nullableString($event, 'download_id'),
            flightNumber: $this->nullableString($metadata, 'flight_number'),
            position: $this->nullableString($metadata, 'position'),
            aircraft: $this->nullableString($metadata, 'aircraft'),
            blockTime: $this->nullableString($metadata, 'block_time'),
            tripId: $this->nullableString($metadata, 'trip_id'),
            crewCount: $this->nullableInt($metadata, 'crew_count'),
            operatingCrewCount: $this->nullableInt($metadata, 'operating_crew_count'),
            deadheadingCrewCount: $this->nullableInt($metadata, 'deadheading_crew_count'),
            dutyStation: $this->nullableString($metadata, 'duty_station'),
            legLocalStart: $this->nullableString($metadata, 'leg_local_start'),
            legLocalEnd: $this->nullableString($metadata, 'leg_local_end'),
            dutyLocalStart: $this->nullableString($metadata, 'duty_local_start'),
            dutyLocalEnd: $this->nullableString($metadata, 'duty_local_end'),
            start: $start,
            end: $end,
            timezone: $this->nullableString($event, 'timezone'),
            origin: $this->nullableString($metadata, 'origin') ?? $this->nullableString($metadata, 'station'),
            destination: $this->nullableString($metadata, 'destination'),
            rawLines: $this->stringList($metadata['raw_lines'] ?? []),
            dutyRawLines: $this->stringList($metadata['duty_raw_lines'] ?? []),
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
        $metadata['flight_number'] = $flight->flightNumber;
        $metadata['origin'] = $flight->origin;
        $metadata['destination'] = $flight->destination;
        $metadata['position'] = $flight->position;
        $metadata['aircraft'] = $flight->aircraft;
        $metadata['tail_number'] = $flight->tailNumber;
        $metadata['block_time'] = $flight->blockTime;
        $metadata['trip_id'] = $flight->tripId;
        $metadata['crew_count'] = $flight->crewCount;
        $metadata['operating_crew_count'] = $flight->operatingCrewCount;
        $metadata['deadheading_crew_count'] = $flight->deadheadingCrewCount;
        $metadata['duty_station'] = $flight->dutyStation;
        $metadata['leg_local_start'] = $flight->legLocalStart;
        $metadata['leg_local_end'] = $flight->legLocalEnd;
        $metadata['duty_local_start'] = $flight->dutyLocalStart;
        $metadata['duty_local_end'] = $flight->dutyLocalEnd;
        $metadata['deadhead'] = $flight->isDeadhead;
        $metadata['flightaware_url'] = $flight->downloadUrl !== '' ? $flight->downloadUrl : ($metadata['flightaware_url'] ?? null);
        $metadata['raw_lines'] = $flight->rawLines;
        $metadata['duty_raw_lines'] = $flight->dutyRawLines;

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
        $durationMinutes = $start->diffInMinutes($end);
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
     * @param mixed $values
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
