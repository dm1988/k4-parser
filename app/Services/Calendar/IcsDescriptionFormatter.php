<?php

namespace App\Services\Calendar;

use App\Enums\MetadataKey;
use App\Enums\ScheduleEventType;
use App\Services\Schedule\Extractor\CrewListParser;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Str;

class IcsDescriptionFormatter
{
    public function __construct(private readonly CrewListParser $crewListParser) {}

    public function format(array $event): string
    {
        $metadata = $this->normalizeCrewMetadata(
            is_array($event['metadata'] ?? null) ? $event['metadata'] : []
        );

        if (ScheduleEventType::isFlightEvent($event)) {
            return $this->formatFlight($event, $metadata);
        }

        $routeLine = $this->formatRouteLine($metadata);

        $lines = [];

        $flightDetails = [];
        $crewInfo = [];
        $timings = [];

        foreach ($metadata as $key => $value) {
            if (in_array($key, [
                MetadataKey::RawLines->value,
                MetadataKey::FlightawareUrl->value,
                MetadataKey::DutyRawLines->value,
                'crew',
                MetadataKey::CrewCount->value,
                MetadataKey::OperatingCrewCount->value,
                MetadataKey::DeadheadingCrewCount->value,
                MetadataKey::Origin->value,
                MetadataKey::Destination->value,
                MetadataKey::LocalStart->value,
                MetadataKey::LocalEnd->value,
            ], true)) {
                continue;
            }

            if ($key === MetadataKey::Deadhead->value && ! $value) {
                continue;
            }

            $stringVal = $this->stringifyMetadataValue($value);
            if ($stringVal === null || $stringVal === '') {
                continue;
            }

            $label = $this->formatMetadataLabel($key);
            $formattedLine = "• {$label}: {$stringVal}";

            if (in_array($key, [MetadataKey::UtcStart->value, MetadataKey::UtcEnd->value], true)) {
                $timings[] = $formattedLine;
            } else {
                $flightDetails[] = "• {$label}: {$stringVal}";
            }
        }

        $crewInfo = $this->formatCrewSection($metadata);

        if ($routeLine !== null) {
            array_unshift($flightDetails, $routeLine);
        }

        if (! empty($flightDetails)) {
            $lines[] = "✈️ FLIGHT DETAILS\n".implode("\n", $flightDetails);
        }

        if (! empty($crewInfo)) {
            $lines[] = "\n👥 CREW LOGISTICS\n".implode("\n", $crewInfo);
        }

        if (! empty($timings)) {
            $lines[] = "\n⏰ TIMES\n".implode("\n", $timings);
        }

        return implode("\n", $lines);
    }

    private function formatFlight(array $event, array $metadata): string
    {
        $sections = [];
        $details = $this->flightDetails($metadata);

        if ($details !== []) {
            $sections[] = "✈️ FLIGHT DETAILS\n".implode("\n", $details);
        }

        $times = [];
        $utcRange = $this->utcRange($event['start'] ?? null, $event['end'] ?? null);
        $blockTime = $this->metadataString($metadata, MetadataKey::BlockTime->value);

        if ($utcRange !== null) {
            $times[] = 'UTC:   '.$utcRange.($blockTime !== null ? "  (Block: {$blockTime})" : '');
        } elseif ($blockTime !== null) {
            $times[] = 'Block: '.$blockTime;
        }

        $localRange = $this->localRange(
            $this->metadataString($metadata, MetadataKey::LegLocalStart->value),
            $this->metadataString($metadata, MetadataKey::LegLocalEnd->value),
        );

        if ($localRange !== null) {
            $times[] = 'Local: '.$localRange;
        }

        if ($times !== []) {
            $sections[] = "⏰ TIMES\n".implode("\n", $times);
        }

        $dutyRange = $this->localRange(
            $this->metadataString($metadata, MetadataKey::DutyLocalStart->value),
            $this->metadataString($metadata, MetadataKey::DutyLocalEnd->value),
        );

        if ($dutyRange !== null) {
            $sections[] = "👨‍✈️ DUTY TIMES\nLocal: ".$dutyRange;
        }

        $crew = $this->compactCrewSection($metadata);

        if ($crew !== []) {
            $sections[] = implode("\n", $crew);
        }

        $notes = $this->additionalFlightNotes($metadata);

        if ($notes !== []) {
            $sections[] = "📝 NOTES\n".implode("\n", $notes);
        }

        return implode("\n\n", $sections);
    }

    /** @return list<string> */
    private function flightDetails(array $metadata): array
    {
        $identity = [];
        $flightNumber = $this->metadataString($metadata, MetadataKey::FlightNumber->value);
        $tail = $this->metadataString($metadata, MetadataKey::TailNumber->value);
        $aircraft = $this->metadataString($metadata, MetadataKey::Aircraft->value);
        $position = $this->metadataString($metadata, MetadataKey::Position->value);

        if ($flightNumber !== null) {
            $identity[] = 'Flight: '.$flightNumber;
        }

        if ($tail !== null) {
            $identity[] = 'Tail: '.$tail.($aircraft !== null ? " ({$aircraft})" : '');
        } elseif ($aircraft !== null) {
            $identity[] = 'Aircraft: '.$aircraft;
        }

        if ($position !== null) {
            $identity[] = 'Pos: '.$position;
        }

        $lines = $identity === [] ? [] : [implode('  |  ', $identity)];
        $origin = $this->airportLabel($metadata, 'origin');
        $destination = $this->airportLabel($metadata, 'destination');

        if ($origin !== null || $destination !== null) {
            $lines[] = 'Route: '.($origin ?? '—').' ➔ '.($destination ?? '—');
        }

        return $lines;
    }

    private function airportLabel(array $metadata, string $side): ?string
    {
        $iata = $this->metadataString($metadata, $side) ?? $this->metadataString($metadata, $side.'_iata');
        $icao = $this->metadataString($metadata, $side.'_icao');

        if ($iata === null) {
            return $icao;
        }

        return $iata.($icao !== null ? " ({$icao})" : '');
    }

    private function utcRange(mixed $startValue, mixed $endValue): ?string
    {
        $start = $this->utcDate($startValue);
        $end = $this->utcDate($endValue);

        if ($start === null && $end === null) {
            return null;
        }

        $includeYear = $start !== null && $end !== null && $start->year !== $end->year;
        $format = $includeYear ? 'M d Y H:i\z' : 'M d H:i\z';

        return ($start?->format($format) ?? '—').' ➔ '.($end?->format(
            $start !== null && $start->isSameDay($end) ? 'H:i\z' : $format,
        ) ?? '—');
    }

    private function utcDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone('UTC');
        } catch (InvalidFormatException) {
            return null;
        }
    }

    private function localRange(?string $start, ?string $end): ?string
    {
        if ($start === null && $end === null) {
            return null;
        }

        $pattern = '/^([A-Z][a-z]{2})\s+(\d{1,2})\s+(\d{2}:\d{2})$/';

        if ($start !== null && $end !== null
            && preg_match($pattern, $start, $startParts) === 1
            && preg_match($pattern, $end, $endParts) === 1
            && $startParts[1] === $endParts[1] && (int) $startParts[2] === (int) $endParts[2]) {
            $end = $endParts[3];
        }

        return ($start ?? '—').' ➔ '.($end ?? '—');
    }

    /** @return list<string> */
    private function compactCrewSection(array $metadata): array
    {
        $crew = $metadata['crew'] ?? [];
        $operatingCount = $metadata[MetadataKey::OperatingCrewCount->value] ?? null;
        $deadheadingCount = $metadata[MetadataKey::DeadheadingCrewCount->value] ?? null;
        $crewCount = $metadata[MetadataKey::CrewCount->value] ?? null;

        if ($crew === [] && $operatingCount === null && $deadheadingCount === null && $crewCount === null) {
            return [];
        }

        $heading = '👥 CREW';

        if ($operatingCount !== null || $deadheadingCount !== null) {
            $heading .= ' ('.($operatingCount ?? '—').' Ops / '.($deadheadingCount ?? '—').' DH)';
        } elseif ($crewCount !== null) {
            $heading .= " ({$crewCount} total)";
        }

        $lines = [$heading];
        $nameWidth = 0;
        $roleWidth = 0;

        foreach ($crew as $member) {
            $nameWidth = max($nameWidth, Str::length($this->metadataString($member, 'name') ?? 'Unknown'));
            $roleWidth = max($roleWidth, Str::length($this->metadataString($member, 'role') ?? ''));
        }

        foreach ($crew as $member) {
            $name = $this->metadataString($member, 'name') ?? 'Unknown';
            $role = $this->metadataString($member, 'role');
            $base = $this->metadataString($member, 'base');
            $employeeId = $this->metadataString($member, 'employee_id');
            $parts = [$this->padColumn($name, $nameWidth)];

            if ($role !== null) {
                $parts[] = $this->padColumn($role, $roleWidth);
            }

            if ($base !== null) {
                $parts[] = $base;
            }

            if ($employeeId !== null) {
                $parts[] = '#'.$employeeId;
            }

            if ($member['deadheading'] && $role !== 'DH') {
                $parts[] = 'DH';
            }

            $lines[] = '• '.rtrim(implode(' │ ', $parts));
        }

        return $lines;
    }

    private function padColumn(string $value, int $width): string
    {
        return $value.str_repeat(' ', max(0, $width - Str::length($value)));
    }

    /** @return list<string> */
    private function additionalFlightNotes(array $metadata): array
    {
        $omittedKeys = [
            MetadataKey::FlightNumber->value, MetadataKey::Origin->value, MetadataKey::Destination->value,
            MetadataKey::Position->value, MetadataKey::Aircraft->value, MetadataKey::TailNumber->value,
            MetadataKey::BlockTime->value, MetadataKey::LegLocalStart->value, MetadataKey::LegLocalEnd->value,
            MetadataKey::DutyLocalStart->value, MetadataKey::DutyLocalEnd->value,
            MetadataKey::Crew->value, MetadataKey::CrewCount->value,
            MetadataKey::OperatingCrewCount->value, MetadataKey::DeadheadingCrewCount->value,
            MetadataKey::UtcStart->value, MetadataKey::UtcEnd->value,
            MetadataKey::LocalStart->value, MetadataKey::LocalEnd->value,
            MetadataKey::RawLines->value, MetadataKey::DutyRawLines->value,
            MetadataKey::FlightawareUrl->value, MetadataKey::DownloadId->value, MetadataKey::DownloadUrl->value,
            MetadataKey::Deadhead->value, MetadataKey::DutyType->value,
            'flight_local_start', 'flight_local_end',
        ];
        $lines = [];

        foreach ($metadata as $key => $value) {
            if (in_array($key, $omittedKeys, true) || Str::startsWith($key, ['origin_', 'destination_'])) {
                continue;
            }

            $value = $this->stringifyMetadataValue($value);

            if ($value !== null) {
                $lines[] = $this->formatMetadataLabel($key).': '.$value;
            }
        }

        return $lines;
    }

    private function metadataString(array $metadata, string $key): ?string
    {
        $value = $metadata[$key] ?? null;

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function formatRouteLine(array $metadata): ?string
    {
        $origin = $metadata[MetadataKey::Origin->value] ?? null;
        $destination = $metadata[MetadataKey::Destination->value] ?? null;

        if (! is_string($origin) || ! is_string($destination) || $origin === '' || $destination === '') {
            return null;
        }

        return "• {$origin} - {$destination}";
    }

    private function formatMetadataLabel(string $key): string
    {
        return str_ireplace('utc', 'UTC', ucfirst(str_replace('_', ' ', $key)));
    }

    private function normalizeCrewMetadata(array $metadata): array
    {
        $crew = is_array($metadata['crew'] ?? null) ? array_values(array_filter($metadata['crew'], is_array(...))) : [];
        $unknownRole = array_any($crew, static fn (array $member): bool => empty($member['role']));
        $unknownDeadhead = array_any($crew, static fn (array $member): bool => ! isset($member['deadheading']) && empty($member['role']));
        $crew = array_map(static fn (array $member): array => [
            ...$member,
            'role' => $member['role'] ?? null,
            'deadheading' => (bool) ($member['deadheading'] ?? (($member['role'] ?? null) === 'DH')),
        ], $crew);
        $summary = $this->crewListParser->summarize($crew);

        if ($unknownRole) {
            $summary['operating_crew_count'] = null;
        }

        if ($unknownDeadhead) {
            $summary['deadheading_crew_count'] = null;
        }

        if ($crew === [] || $summary['crew_count'] === null) {
            $candidateLines = [];

            foreach ([MetadataKey::DutyRawLines->value, MetadataKey::RawLines->value] as $key) {
                if (is_array($metadata[$key] ?? null)) {
                    $candidateLines = array_merge($candidateLines, $metadata[$key]);
                }
            }

            if ($candidateLines !== []) {
                $parsed = $this->crewListParser->parseWithSummary($candidateLines);

                if ($crew === [] && $parsed['crew'] !== []) {
                    $crew = $parsed['crew'];
                }

                if ($summary['crew_count'] === null && $parsed['crew_count'] !== null) {
                    $summary = [
                        'crew_count' => $parsed['crew_count'],
                        'operating_crew_count' => $parsed['operating_crew_count'],
                        'deadheading_crew_count' => $parsed['deadheading_crew_count'],
                    ];
                }
            }
        }

        if ($crew !== []) {
            $metadata['crew'] = $crew;
        }

        foreach ([MetadataKey::CrewCount->value, MetadataKey::OperatingCrewCount->value, MetadataKey::DeadheadingCrewCount->value] as $key) {
            if (($metadata[$key] ?? null) === null && $summary[$key] !== null) {
                $metadata[$key] = $summary[$key];
            }
        }

        return $metadata;
    }

    private function formatCrewSection(array $metadata): array
    {
        $lines = [];

        foreach ([MetadataKey::CrewCount, MetadataKey::OperatingCrewCount, MetadataKey::DeadheadingCrewCount] as $metaKey) {
            $value = $metadata[$metaKey->value] ?? null;

            if ($value === null) {
                continue;
            }

            $label = $metaKey->metadataLabel() ?? ucfirst(str_replace('_', ' ', $metaKey->value));
            $lines[] = $metaKey->metadataPrefix().$label.$metaKey->metadataSuffix().$value;
        }

        $crew = is_array($metadata['crew'] ?? null) ? $metadata['crew'] : [];

        if ($crew === []) {
            return $lines;
        }

        $lines[] = '• Crew Members:';

        foreach ($crew as $member) {
            if (! is_array($member)) {
                continue;
            }

            $parts = [];
            $name = $member['name'] ?? 'Unknown';
            $role = $member['role'] ?? null;
            $base = $member['base'] ?? null;
            $employeeId = $member['employee_id'] ?? null;
            $deadheading = ($member['deadheading'] ?? false) ? 'DH' : null;

            if ($role) {
                $parts[] = $role;
            }

            if ($base) {
                $parts[] = $base;
            }

            if ($employeeId) {
                $parts[] = '#'.$employeeId;
            }

            if ($deadheading && $role !== 'DH') {
                $parts[] = $deadheading;
            }

            $suffix = $parts === [] ? '' : ' ('.implode(' • ', $parts).')';
            $lines[] = "  └─ {$name}{$suffix}";
        }

        return $lines;
    }

    private function stringifyMetadataValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $parts = [];

        foreach ($value as $key => $item) {
            $item = $this->stringifyMetadataValue($item);

            if ($item === null || $item === '') {
                continue;
            }

            if (is_string($key)) {
                $parts[] = ucfirst(str_replace('_', ' ', $key)).': '.$item;

                continue;
            }

            $parts[] = $item;
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
