<?php

namespace App\Services\Calendar;

use App\DTOs\Flight;
use App\Enums\DutyEventType;
use App\Enums\MetadataKey;
use App\Enums\ScheduleEventType;
use App\Mappers\FlightMapper;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

final class FlightDutyEvent
{
    private const int MAX_LOCAL_UTC_OFFSET_MINUTES = 14 * 60;

    /** A plausibility limit for exported data, not a regulatory duty limit. */
    private const int MAX_DUTY_MINUTES = 30 * 60;

    public function __construct(
        private readonly FlightMapper $flightMapper,
    ) {}

    /**
     * @return array{title: string, type: string, start: string, end: string, timezone: string, metadata: array<string, mixed>}|null
     */
    public function buildFromFlight(mixed $event): ?array
    {
        $event = $this->normalizeEvent($event);

        if ($event === null || ! ScheduleEventType::isFlightEvent($event)) {
            return null;
        }

        $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        $flightStartUtc = $this->parseUtc($event['start'] ?? null);
        $flightEndUtc = $this->parseUtc($event['end'] ?? null);

        if ($flightStartUtc === null || $flightEndUtc === null || $flightEndUtc->lessThanOrEqualTo($flightStartUtc)) {
            return null;
        }

        $flightLocalStartValue = $this->eventValue($event, $metadata, 'legLocalStart', MetadataKey::LegLocalStart->value);
        $flightLocalEndValue = $this->eventValue($event, $metadata, 'legLocalEnd', MetadataKey::LegLocalEnd->value);
        $dutyLocalStartValue = $this->eventValue($event, $metadata, 'dutyLocalStart', MetadataKey::DutyLocalStart->value);
        $dutyLocalEndValue = $this->eventValue($event, $metadata, 'dutyLocalEnd', MetadataKey::DutyLocalEnd->value);

        $flightLocalStart = $this->parseLocalTime($flightLocalStartValue, $flightStartUtc);
        $flightLocalEnd = $this->parseLocalTime($flightLocalEndValue, $flightEndUtc);

        if ($flightLocalStart === null || $flightLocalEnd === null
            || abs($this->minutesBetween($flightStartUtc, $flightLocalStart)) > self::MAX_LOCAL_UTC_OFFSET_MINUTES
            || abs($this->minutesBetween($flightEndUtc, $flightLocalEnd)) > self::MAX_LOCAL_UTC_OFFSET_MINUTES) {
            return null;
        }

        $dutyLocalStart = $this->parseLocalTime($dutyLocalStartValue, $flightLocalStart);
        $dutyLocalEnd = $this->parseLocalTime($dutyLocalEndValue, $flightLocalEnd);

        if ($dutyLocalStart === null || $dutyLocalEnd === null || $dutyLocalStart->greaterThan($flightLocalStart)) {
            return null;
        }

        $dutyLocalEnd = $this->shiftForwardAfter($dutyLocalEnd, $flightLocalEnd);

        if ($dutyLocalEnd === null) {
            return null;
        }

        $dutyStartUtc = $flightStartUtc->subMinutes($this->minutesBetween($dutyLocalStart, $flightLocalStart));
        $dutyEndUtc = $flightEndUtc->addMinutes($this->minutesBetween($flightLocalEnd, $dutyLocalEnd));

        if ($this->minutesBetween($dutyStartUtc, $dutyEndUtc) > self::MAX_DUTY_MINUTES) {
            return null;
        }

        $durationLabel = $this->formatDuration($dutyStartUtc->diffInMinutes($dutyEndUtc));

        return [
            'title' => 'Duty',
            'type' => ScheduleEventType::Duty->value,
            'start' => $dutyStartUtc->toIso8601String(),
            'end' => $dutyEndUtc->toIso8601String(),
            'timezone' => 'UTC',
            'metadata' => [
                MetadataKey::DutyType->value => DutyEventType::FlightDuty->value,
                MetadataKey::FlightNumber->value => $this->eventValue($event, $metadata, 'flightNumber', MetadataKey::FlightNumber->value),
                MetadataKey::Origin->value => $this->eventValue($event, $metadata, 'origin'),
                MetadataKey::Destination->value => $this->eventValue($event, $metadata, 'destination'),
                MetadataKey::DutyUtcStart->value => $this->formatUtcNote($dutyStartUtc),
                MetadataKey::DutyUtcEnd->value => $this->formatUtcNote($dutyEndUtc),
                MetadataKey::DutyLocalStart->value => $dutyLocalStartValue,
                MetadataKey::DutyLocalEnd->value => $dutyLocalEndValue,
                MetadataKey::FlightUtcStart->value => $this->formatUtcNote($flightStartUtc),
                MetadataKey::FlightUtcEnd->value => $this->formatUtcNote($flightEndUtc),
                MetadataKey::LegLocalStart->value => $flightLocalStartValue,
                MetadataKey::LegLocalEnd->value => $flightLocalEndValue,
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
        } catch (InvalidFormatException) {
            return null;
        }
    }

    private function parseLocalTime(mixed $value, CarbonImmutable $reference): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '' || str_contains($value, "\0")) {
            return null;
        }

        $value = trim($value);
        $closest = null;

        foreach ([$reference->year - 1, $reference->year, $reference->year + 1] as $year) {
            $input = $year.' '.$value;

            try {
                $candidate = CarbonImmutable::createFromFormat('!Y M j H:i', $input, 'UTC');
            } catch (InvalidFormatException) {
                continue;
            }

            if ($candidate === null || ($candidate->format('Y M j H:i') !== $input && $candidate->format('Y M d H:i') !== $input)) {
                continue;
            }

            if ($closest === null
                || abs($candidate->getTimestamp() - $reference->getTimestamp()) < abs($closest->getTimestamp() - $reference->getTimestamp())) {
                $closest = $candidate;
            }
        }

        return $closest;
    }

    private function minutesBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60);
    }

    private function shiftForwardAfter(CarbonImmutable $value, CarbonImmutable $reference): ?CarbonImmutable
    {
        if ($value->lessThan($reference)) {
            $value = $value->addDay();
        }

        return $value->lessThan($reference) ? null : $value;
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
