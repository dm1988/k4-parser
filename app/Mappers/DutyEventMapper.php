<?php

namespace App\Mappers;

use App\DTOs\DutyEvent;
use App\Enums\MetadataKey;
use App\Enums\ParserEventType;
use Carbon\CarbonImmutable;

final class DutyEventMapper
{
    public function fromCalendarEvent(array $event, ?string $downloadId = null): ?DutyEvent
    {
        $eventType = ParserEventType::fromValue((string) ($event['type'] ?? null));

        if ($eventType !== ParserEventType::Duty) {
            return null;
        }

        $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        $start = $this->nullableString($event, 'start');
        $end = $this->nullableString($event, 'end');
        [$scheduleLabel, $durationLabel] = $this->buildTimeLabels(
            $start,
            $end,
            $this->nullableString($event, 'scheduleLabel', 'schedule_label'),
            $this->nullableString($event, 'durationLabel', 'duration_label'),
        );

        return new DutyEvent(
            title: (string) ($event['title'] ?? 'Untitled event'),
            type: $eventType->value,
            typeLabel: (string) ($event['typeLabel'] ?? $event['type_label'] ?? $eventType->label()),
            typeDescription: (string) ($event['typeDescription'] ?? $event['type_description'] ?? $eventType->description()),
            typeIcon: (string) ($event['typeIcon'] ?? $event['type_icon'] ?? $eventType->icon()),
            scheduleLabel: $scheduleLabel,
            durationLabel: $durationLabel,
            isDeadhead: (bool) ($metadata[MetadataKey::Deadhead->value] ?? $event['is_deadhead'] ?? false),
            badgeColor: (string) ($event['badgeColor'] ?? $event['badge_color'] ?? $eventType->badgeColor()),
            downloadUrl: (string) ($metadata[MetadataKey::DownloadUrl->value] ?? $event['download_url'] ?? ''),
            downloadId: $downloadId ?? $this->nullableString($event, MetadataKey::DownloadId->value),
            station: $this->nullableString($metadata, MetadataKey::Station->value),
            activityCode: $this->nullableString($metadata, MetadataKey::ActivityCode->value),
            layoverDuration: $this->nullableString($metadata, MetadataKey::LayoverDuration->value),
            crewCount: $this->nullableInt($metadata, MetadataKey::CrewCount->value),
            operatingCrewCount: $this->nullableInt($metadata, MetadataKey::OperatingCrewCount->value),
            deadheadingCrewCount: $this->nullableInt($metadata, MetadataKey::DeadheadingCrewCount->value),
            start: $start,
            end: $end,
            timezone: $this->nullableString($event, 'timezone'),
            rawLines: $this->stringList($metadata[MetadataKey::RawLines->value] ?? []),
            metadata: $metadata,
        );
    }

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
}
