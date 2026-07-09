<?php

namespace App\Services;

use App\DTOs\Flight;
use App\Enums\ParserEventType;
use App\Mappers\FlightMapper;
use Carbon\CarbonImmutable;
use Throwable;

final class FlightDutyCalendarEventService
{
    public function __construct(
        private readonly FlightMapper $flightMapper,
    ) {}

    /**
     * @return array{title: string, type: string, start: string, end: string, timezone: string, metadata: array<string, mixed>}|null
     */
    public function buildFromFlight(mixed $event): ?array
    {
        $event = $this->normalizeEvent($event);

        if ($event === null || ! ParserEventType::fromEvent($event)->isFlightLike()) {
            return null;
        }

        $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        $flightStartUtc = $this->parseUtc($event['start'] ?? null);
        $flightEndUtc = $this->parseUtc($event['end'] ?? null);

        if ($flightStartUtc === null || $flightEndUtc === null) {
            return null;
        }

        $flightLocalStartValue = $this->eventValue($event, $metadata, 'legLocalStart', 'leg_local_start');
        $flightLocalEndValue = $this->eventValue($event, $metadata, 'legLocalEnd', 'leg_local_end');
        $dutyLocalStartValue = $this->eventValue($event, $metadata, 'dutyLocalStart', 'duty_local_start');
        $dutyLocalEndValue = $this->eventValue($event, $metadata, 'dutyLocalEnd', 'duty_local_end');

        $flightLocalStart = $this->parseLocalTime($flightLocalStartValue, $flightStartUtc);
        $flightLocalEnd = $this->parseLocalTime($flightLocalEndValue, $flightEndUtc);
        $dutyLocalStart = $this->parseLocalTime($dutyLocalStartValue, $flightStartUtc);
        $dutyLocalEnd = $this->parseLocalTime($dutyLocalEndValue, $flightEndUtc);

        if ($flightLocalStart === null || $flightLocalEnd === null || $dutyLocalStart === null || $dutyLocalEnd === null) {
            return null;
        }

        $flightLocalEnd = $this->shiftForwardAfter($flightLocalEnd, $flightLocalStart);
        $dutyLocalEnd = $this->shiftForwardAfter($dutyLocalEnd, $flightLocalEnd);

        $dutyStartUtc = $flightStartUtc->subMinutes($this->minutesBetween($dutyLocalStart, $flightLocalStart));
        $dutyEndUtc = $flightEndUtc->addMinutes($this->minutesBetween($flightLocalEnd, $dutyLocalEnd));

        if ($dutyEndUtc->lessThanOrEqualTo($dutyStartUtc)) {
            return null;
        }

        $durationLabel = $this->formatDuration($dutyStartUtc->diffInMinutes($dutyEndUtc));

        return [
            'title' => 'Duty',
            'type' => ParserEventType::Duty->value,
            'start' => $dutyStartUtc->toIso8601String(),
            'end' => $dutyEndUtc->toIso8601String(),
            'timezone' => 'UTC',
            'metadata' => [
                'flight_number' => $this->eventValue($event, $metadata, 'flightNumber', 'flight_number'),
                'origin' => $this->eventValue($event, $metadata, 'origin'),
                'destination' => $this->eventValue($event, $metadata, 'destination'),
                'duty_utc_start' => $this->formatUtcNote($dutyStartUtc),
                'duty_utc_end' => $this->formatUtcNote($dutyEndUtc),
                'duty_local_start' => $dutyLocalStartValue,
                'duty_local_end' => $dutyLocalEndValue,
                'flight_utc_start' => $this->formatUtcNote($flightStartUtc),
                'flight_utc_end' => $this->formatUtcNote($flightEndUtc),
                'flight_local_start' => $flightLocalStartValue,
                'flight_local_end' => $flightLocalEndValue,
                'duration' => $durationLabel,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeEvent(mixed $event): ?array
    {
        if ($event instanceof Flight) {
            return $this->flightMapper->toCalendarEvent($event);
        }

        return is_array($event) ? $event : null;
    }

    private function eventValue(array $event, array $metadata, string $primaryKey, ?string $secondaryKey = null): mixed
    {
        return $metadata[$primaryKey]
            ?? ($secondaryKey !== null ? ($metadata[$secondaryKey] ?? null) : null)
            ?? $event[$primaryKey]
            ?? ($secondaryKey !== null ? ($event[$secondaryKey] ?? null) : null);
    }

    private function parseUtc(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone('UTC');
        } catch (Throwable) {
            return null;
        }
    }

    private function parseLocalTime(mixed $value, CarbonImmutable $reference): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('!Y M j H:i', $reference->year.' '.trim($value), 'UTC');

        return $parsed === false ? null : $parsed;
    }

    private function minutesBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60);
    }

    private function shiftForwardAfter(CarbonImmutable $value, CarbonImmutable $reference): CarbonImmutable
    {
        while ($value->lessThan($reference)) {
            $value = $value->addDay();
        }

        return $value;
    }

    private function formatDuration(float $durationMinutes): string
    {
        $durationMinutes = (int) $durationMinutes;
        $hours = intdiv($durationMinutes, 60);
        $minutes = $durationMinutes % 60;

        return "{$hours}h {$minutes}m";
    }

    private function formatUtcNote(CarbonImmutable $value): string
    {
        return $value->format('M j H:i').' Z';
    }
}
