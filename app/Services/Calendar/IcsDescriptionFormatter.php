<?php

namespace App\Services\Calendar;

use App\Enums\MetadataKey;
use App\Services\Schedule\Extractor\CrewListParser;

class IcsDescriptionFormatter
{
    public function __construct(private readonly CrewListParser $crewListParser) {}

    public function format(array $event): string
    {
        $metadata = $this->normalizeCrewMetadata(
            is_array($event['metadata'] ?? null) ? $event['metadata'] : []
        );
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
        $crew = is_array($metadata['crew'] ?? null) ? $metadata['crew'] : [];
        $summary = $this->crewListParser->summarize($crew);

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
