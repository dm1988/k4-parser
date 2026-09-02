<?php

namespace App\Services\FlightPlan\Extractor;

use App\Exceptions\FlightPlanDataConflictException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

class FlightIdentityExtractor
{
    /**
     * @return array{
     *     data: array{flight_number: ?string, trip_number: ?string, recall_number: ?string, aircraft_type: ?string, tail_number: ?string, flight_date: ?string, release_revision: ?string},
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $header = $this->headerValues($text);
        $fpl = $this->fplValues($text);

        $flightNumber = $this->corroborate('flight number', $header['flight_number'], $fpl['flight_number']);
        $tailNumber = $this->corroborate('tail number', $header['tail_number'], $fpl['tail_number']);
        $flightDate = $this->corroborate('flight date', $header['flight_date'], $fpl['flight_date']);

        return [
            'data' => [
                'flight_number' => $flightNumber,
                'trip_number' => $header['trip_number'],
                'recall_number' => $header['recall_number'],
                'aircraft_type' => $header['aircraft_type'] ?? $fpl['aircraft_type'],
                'tail_number' => $tailNumber,
                'flight_date' => $flightDate,
                'release_revision' => null,
            ],
            'source_fragments' => array_filter([
                'identity_header' => $header['source'],
                'icao_flight_plan' => $fpl['source'],
            ], static fn (?string $value): bool => $value !== null),
        ];
    }

    /**
     * @return array{flight_number: ?string, trip_number: ?string, recall_number: ?string, aircraft_type: ?string, tail_number: ?string, flight_date: ?string, source: ?string}
     */
    private function headerValues(string $text): array
    {
        $headerPattern = '/KALITTA\s+AIR\s+TRIP\s+(\d+)\s+RECALL\s+(\d+)\s+'
            .'([A-Z0-9-]+)\s+([A-Z0-9-]+)\s+(\d{2}\/\d{2}\/\d{2})/i';
        $headerMatches = [];
        preg_match($headerPattern, $text, $headerMatches);

        $flightMatches = [];
        preg_match('/\bETA\s+\d{2}[.:]\d{2}Z(?:\/\d{1,2})?\s+([A-Z]{2,3}\s*\d{1,4})\b/i', $text, $flightMatches);

        return [
            'flight_number' => $this->normalizeFlightNumber($flightMatches[1] ?? null),
            'trip_number' => $this->nullableMatch($headerMatches, 1),
            'recall_number' => $this->recallNumber($headerMatches[2] ?? null),
            'tail_number' => $this->normalizeUpper($headerMatches[3] ?? null),
            'aircraft_type' => $this->normalizeUpper($headerMatches[4] ?? null),
            'flight_date' => $this->dateFromFormat($headerMatches[5] ?? null, '!m/d/y'),
            'source' => isset($headerMatches[0]) ? Str::squish($headerMatches[0]) : null,
        ];
    }

    private function recallNumber(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{5}$/', $value) === 1
            ? $value
            : null;
    }

    /**
     * @return array{flight_number: ?string, aircraft_type: ?string, tail_number: ?string, flight_date: ?string, source: ?string}
     */
    private function fplValues(string $text): array
    {
        $blockMatches = [];
        preg_match('/\(FPL-.*?\)/s', $text, $blockMatches);
        $block = $blockMatches[0] ?? '';

        $flightMatches = [];
        preg_match('/\(FPL-([A-Z0-9]+)-/i', $block, $flightMatches);
        $aircraftMatches = [];
        preg_match('/\(FPL-[^-]+-[^-]+\s*-\s*([A-Z0-9]+)\//is', $block, $aircraftMatches);
        $tailMatches = [];
        preg_match('/\bREG\/([A-Z0-9-]+)/i', $block, $tailMatches);
        $dateMatches = [];
        preg_match('/\bDOF\/(\d{6})\b/i', $block, $dateMatches);

        return [
            'flight_number' => $this->normalizeFlightNumber($flightMatches[1] ?? null),
            'aircraft_type' => $this->normalizeUpper($aircraftMatches[1] ?? null),
            'tail_number' => $this->normalizeUpper($tailMatches[1] ?? null),
            'flight_date' => $this->dateFromFormat($dateMatches[1] ?? null, '!ymd'),
            'source' => $block !== '' ? Str::squish($block) : null,
        ];
    }

    private function corroborate(string $field, ?string $preferred, ?string $corroborating): ?string
    {
        if ($preferred !== null && $corroborating !== null && $preferred !== $corroborating) {
            throw FlightPlanDataConflictException::forField($field);
        }

        return $preferred ?? $corroborating;
    }

    private function dateFromFormat(mixed $value, string $format): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat($format, $value, 'UTC');
        } catch (Throwable) {
            return null;
        }

        return $date->format(ltrim($format, '!')) === $value ? $date->toDateString() : null;
    }

    /** @param array<int, string> $matches */
    private function nullableMatch(array $matches, int $index): ?string
    {
        return isset($matches[$index]) && $matches[$index] !== '' ? $matches[$index] : null;
    }

    private function normalizeUpper(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? Str::upper(trim($value)) : null;
    }

    private function normalizeFlightNumber(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== ''
            ? Str::upper(str_replace(' ', '', trim($value)))
            : null;
    }
}
