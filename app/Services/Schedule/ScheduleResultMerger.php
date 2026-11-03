<?php

namespace App\Services\Schedule;

use App\DTOs\ExtractedEventDTO;
use App\DTOs\Flight;
use App\Enums\DutyEventType;
use App\Enums\ScheduleEventType;
use App\Mappers\FlightMapper;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Carbon\Exceptions\InvalidTimeZoneException;

final class ScheduleResultMerger
{
    public function __construct(private readonly FlightMapper $flightMapper) {}

    /**
     * @param  list<array<string, mixed>>  $results
     * @param  list<array<string, mixed>>  $sources
     * @return array<string, mixed>
     */
    public function merge(array $results, array $sources): array
    {
        $groups = [];
        $provenance = [];
        $summaries = [];
        $conflicts = [];
        $sourceOccurrences = [];

        foreach ($results as $sourceIndex => $result) {
            $source = $sources[$sourceIndex] ?? [];
            $sourceHash = hash('sha256', ($source['filename'] ?? '').'|'.($source['raw_text'] ?? $sourceIndex));
            $sourceOccurrence = $sourceOccurrences[$sourceHash] ?? 0;
            $sourceOccurrences[$sourceHash] = $sourceOccurrence + 1;
            $sourceId = $sourceHash.':'.$sourceOccurrence;
            $summary = $result;
            unset($summary['calendar_events']);
            $provenance[] = [
                'id' => $sourceId,
                'position' => $source['position'] ?? $sourceIndex + 1,
                'filename' => $source['filename'] ?? null,
                'source' => $source['source'] ?? null,
                'document_type' => $source['document_type'] ?? null,
                'mime' => $source['mime'] ?? null,
                'meta' => $source['meta'] ?? null,
                'data' => $summary,
            ];
            $summaries[] = ['source_id' => $sourceId, 'value' => $summary];
            /** Match repeated events across sources without collapsing repetitions inside one source. */
            $occurrences = [];

            foreach (($result['calendar_events'] ?? []) as $eventIndex => $event) {
                $original = $event instanceof ExtractedEventDTO ? $event->toArray() : $event;

                if (! is_array($original)) {
                    continue;
                }

                $data = $this->normalizeEvent($event instanceof Flight ? $this->flightMapper->toCalendarEvent($event) : $original);
                $identity = $this->identity($data);
                $identityKey = $identity === null ? $sourceId.':'.$eventIndex : $this->key($identity);
                $occurrence = $occurrences[$identityKey] ?? 0;
                $occurrences[$identityKey] = $occurrence + 1;
                $groupKey = $identityKey.':'.$occurrence;
                $groups[$groupKey]['identity'] = $identity;
                $groups[$groupKey]['observations'][] = [
                    'source_id' => $sourceId,
                    'event_index' => $eventIndex,
                    'event' => $original,
                    'data' => $data,
                ];
            }
        }

        $parsed = $this->reconcile($summaries, '', 'result', $conflicts) ?? [];
        ksort($groups);
        $events = [];

        foreach ($groups as $groupKey => $group) {
            $observations = $group['observations'];
            usort($observations, fn (array $left, array $right): int => strcmp($this->key($left), $this->key($right)));
            $event = $observations[0]['data'];
            $event['metadata'] = $this->reconcile(array_map(
                static fn (array $observation): array => ['source_id' => $observation['source_id'], 'value' => $observation['data']['metadata']],
                $observations,
            ), 'metadata', $groupKey, $conflicts) ?? [];
            $event['metadata']['source_observations'] = array_map(static function (array $observation): array {
                unset($observation['data']);

                return $observation;
            }, $observations);
            $events[$groupKey] = $event;
        }

        $keys = array_keys($groups);

        foreach ($keys as $index => $key) {
            foreach (array_slice($keys, $index + 1) as $otherKey) {
                if ($this->observationsConflict($groups[$key], $groups[$otherKey])) {
                    $conflicts[] = [
                        'kind' => 'event',
                        'events' => [$key, $otherKey],
                        'titles' => [$events[$key]['title'], $events[$otherKey]['title']],
                        'observations' => array_merge($events[$key]['metadata']['source_observations'], $events[$otherKey]['metadata']['source_observations']),
                    ];
                }
            }
        }

        uasort($events, fn (array $left, array $right): int => strcmp((string) $left['start'], (string) $right['start'])
            ?: strcmp($this->key($this->identity($left)), $this->key($this->identity($right))));
        usort($provenance, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));
        usort($conflicts, fn (array $left, array $right): int => strcmp($this->key($left), $this->key($right)));

        return [...$parsed, 'calendar_events' => array_values($events), 'sources' => $provenance, 'merge_conflicts' => $conflicts];
    }

    /** @return array<string, mixed> */
    private function normalizeEvent(array $event): array
    {
        $metadata = $event['metadata'] ?? $event['meta'] ?? [];
        $metadata = is_array($metadata) ? $metadata : [];

        foreach (['flight_number' => 'flightNumber', 'origin' => 'origin', 'destination' => 'destination',
            'station' => 'station', 'duty_type' => 'dutyType', 'activity_code' => 'activityCode',
            'tail_number' => 'tailNumber', 'raw_lines' => 'rawLines', 'duty_raw_lines' => 'dutyRawLines',
            'crew_count' => 'crewCount', 'operating_crew_count' => 'operatingCrewCount',
            'deadheading_crew_count' => 'deadheadingCrewCount', 'layover_duration' => 'layoverDuration',
            'trip_id' => 'tripId', 'hotel' => 'hotel', 'position' => 'position'] as $key => $alias) {
            $metadata[$key] = $metadata[$key] ?? $event[$alias] ?? $event[$key] ?? null;
        }

        foreach (['flight_number', 'origin', 'destination', 'station', 'activity_code'] as $key) {
            if (is_string($metadata[$key] ?? null)) {
                $metadata[$key] = $this->label($metadata[$key]);
            }
        }

        $event['metadata'] = $metadata;
        $type = ScheduleEventType::fromEvent($event);
        $start = $this->utcTime($event['start'] ?? null, $event['timezone'] ?? null);
        $end = $this->utcTime($event['end'] ?? null, $event['timezone'] ?? null);

        if ($type === ScheduleEventType::Duty) {
            $metadata['duty_type'] = DutyEventType::fromEvent($event)->value;
        }

        return [
            'type' => $type->value,
            'title' => (string) ($event['title'] ?? ''),
            'start' => $start ?? ($event['start'] ?? null),
            'end' => $end ?? ($event['end'] ?? null),
            'timezone' => $start !== null && $end !== null ? 'UTC' : ($event['timezone'] ?? 'UTC'),
            'metadata' => array_filter($metadata, static fn (mixed $value): bool => $value !== null),
        ];
    }

    /** @return array{kind: string, details: list<string>, start: string, end: string}|null */
    private function identity(array $event): ?array
    {
        $start = $this->utcTime($event['start'] ?? null, $event['timezone'] ?? null);
        $end = $this->utcTime($event['end'] ?? null, $event['timezone'] ?? null);

        if ($start === null || $end === null) {
            return null;
        }

        $type = ScheduleEventType::fromEvent($event);

        if ($type === ScheduleEventType::Unknown) {
            return null;
        }

        $metadata = $event['metadata'];
        $title = $this->label($event['title']);
        $station = $this->label($metadata['station'] ?? '');
        $kind = $type->value;

        if (ScheduleEventType::isFlightEvent($event)) {
            $kind .= ':'.DutyEventType::fromEvent($event)->value;
            $details = array_map($this->label(...), [$metadata['flight_number'] ?? '', $metadata['origin'] ?? '', $metadata['destination'] ?? '']);

            if (in_array('', $details, true)) {
                return null;
            }
        } else {
            $details = match ($type) {
                ScheduleEventType::Duty => [DutyEventType::fromEvent($event)->value, $station, $title],
                ScheduleEventType::Layover => [$station, $this->label($metadata['hotel'] ?? ''), $title],
                default => [$station, $title],
            };

            if ($title === '' && $station === '') {
                return null;
            }
        }

        return ['kind' => $kind, 'details' => $details, 'start' => $start, 'end' => $end];
    }

    private function observationsConflict(array $left, array $right): bool
    {
        $first = $left['identity'];
        $second = $right['identity'];

        if ($first === null || $second === null || $first === $second || $first['kind'] !== $second['kind']) {
            return false;
        }

        $sourceIds = array_unique(array_column(array_merge($left['observations'], $right['observations']), 'source_id'));

        if (count($sourceIds) < 2) {
            return false;
        }

        $sameDetails = $first['details'] === $second['details'];

        if (str_starts_with($first['kind'], 'flight:') || $first['kind'] === 'duty:deadhead') {
            if ($first['details'][0] !== $second['details'][0]) {
                return false;
            }

            $sameDetails = true;
        }

        return $sameDetails && ($first['start'] === $second['start'] || $first['end'] === $second['end']
            || ($first['start'] < $second['end'] && $second['start'] < $first['end']));
    }

    /**
     * @param  list<array{source_id: string, value: mixed}>  $observations
     * @param  list<array<string, mixed>>  $conflicts
     */
    private function reconcile(array $observations, string $path, string $scope, array &$conflicts): mixed
    {
        $empty = $observations[0]['value'] ?? null;
        $observations = array_values(array_filter($observations, static fn (array $observation): bool => ! in_array($observation['value'], [null, '', []], true)));

        if ($observations === []) {
            return is_array($empty) ? [] : null;
        }

        $unique = [];

        foreach ($observations as $observation) {
            $unique[$this->key($observation['value'])] = $observation['value'];
        }

        ksort($unique);

        if (count($unique) === 1) {
            return $this->canonicalValue(reset($unique));
        }

        $values = array_values($unique);

        if (count(array_filter($values, is_array(...))) === count($values)) {
            $lists = array_filter($values, array_is_list(...));

            if (count($lists) === count($values) && array_filter(array_merge(...$lists), is_array(...)) === []) {
                $items = [];

                foreach ($values as $list) {
                    foreach ($list as $item) {
                        $items[$this->key($item)] = $this->canonicalValue($item);
                    }
                }

                ksort($items);

                return array_values($items);
            }

            if ($lists === []) {
                $merged = [];
                $keys = array_unique(array_merge(...array_map(array_keys(...), $values)));
                sort($keys);

                foreach ($keys as $key) {
                    $children = [];

                    foreach ($observations as $observation) {
                        if (array_key_exists($key, $observation['value'])) {
                            $children[] = ['source_id' => $observation['source_id'], 'value' => $observation['value'][$key]];
                        }
                    }

                    $merged[$key] = $this->reconcile($children, ltrim($path.'.'.$key, '.'), $scope, $conflicts);
                }

                return $merged;
            }
        }

        usort($observations, fn (array $left, array $right): int => strcmp($this->key($left), $this->key($right)));
        $conflicts[] = ['kind' => 'field', 'scope' => $scope, 'field' => $path, 'observations' => $observations];

        return null;
    }

    private function utcTime(mixed $value, mixed $timezone): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, is_string($timezone) ? $timezone : 'UTC')->setTimezone('UTC')->toIso8601String();
        } catch (InvalidFormatException|InvalidTimeZoneException) {
            return null;
        }
    }

    private function label(mixed $value): string
    {
        return is_string($value) ? mb_strtoupper(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value)) : '';
    }

    private function key(mixed $value): string
    {
        return json_encode($this->canonicalValue($value), JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function canonicalValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($this->canonicalValue(...), $value);
        }

        return $value;
    }
}
