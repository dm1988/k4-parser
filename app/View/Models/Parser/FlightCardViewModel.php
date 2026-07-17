<?php

namespace App\View\Models\Parser;

use App\DTOs\Flight;
use App\Enums\ParserEventType;
use Carbon\CarbonImmutable;

readonly class FlightCardViewModel
{
    public function __construct(
        public Flight $flight,
    ) {}

    public static function fromFlight(Flight $flight): self
    {
        return new self($flight);
    }

    public function heading(): string
    {
        if (ParserEventType::fromValue($this->flight->type)->isFlightLike() && $this->flight->flightNumber) {
            return $this->flight->flightNumber;
        }

        return $this->flight->typeLabel;
    }

    public function headingDateLabel(): string
    {
        if ($this->flight->start) {
            return CarbonImmutable::parse($this->flight->start)->format('M j');
        }

        return $this->flight->scheduleLabel;
    }

    // Flight Details
    public function originLabel(): string
    {
        return $this->flight->origin ?? 'UNK';
    }

    public function destinationLabel(): string
    {
        return $this->flight->destination ?? 'UNK';
    }

    public function originIata(): string
    {
        return $this->flight->origin ?? '---';
    }

    public function destinationIata(): string
    {
        return $this->flight->destination ?? '---';
    }

    public function originTimeLabel(): string
    {
        return $this->formatTime($this->flight->start);
    }

    public function destinationTimeLabel(): string
    {
        return $this->formatTime($this->flight->end);
    }

    public function originCardTimeLabel(): string
    {
        return $this->formatCardTime($this->flight->start);
    }

    public function destinationCardTimeLabel(): string
    {
        return $this->formatCardTime($this->flight->end);
    }

    public function dutyDownloadUrl(): ?string
    {
        if (! $this->showsDutyDownload()) {
            return null;
        }

        $parameters = ['eventId' => $this->flight->downloadId];
        $parseKey = $this->downloadParseKey();

        if ($parseKey !== null) {
            $parameters['parse_key'] = $parseKey;
        }

        return route('parse.export.event.duty', $parameters);
    }

    public function hasDutyCalendarDownload(): bool
    {
        return $this->flight->downloadId !== null
            && $this->flight->start !== null
            && $this->flight->end !== null
            && $this->legLocalStartLabel() !== '—'
            && $this->legLocalEndLabel() !== '—'
            && $this->dutyLocalStartLabel() !== '—'
            && $this->dutyLocalEndLabel() !== '—';
    }

    public function showsDutyDownload(): bool
    {
        return (auth()->user()?->canExportScheduleParserDuty() ?? false)
            && $this->hasDutyCalendarDownload();
    }

    public function hasLegLocalTimes(): bool
    {
        return $this->legLocalStartLabel() !== '—'
            || $this->legLocalEndLabel() !== '—';
    }

    public function legLocalStartLabel(): string
    {
        return $this->flight->legLocalStart ?: '—';
    }

    public function legLocalEndLabel(): string
    {
        return $this->flight->legLocalEnd ?: '—';
    }

    public function legLocalTimesLabel(): string
    {
        return $this->formatRangeLabel(
            $this->legLocalStartLabel(),
            $this->legLocalEndLabel(),
        );
    }

    public function hasDutyLocalTimes(): bool
    {
        return $this->dutyLocalStartLabel() !== '—'
            || $this->dutyLocalEndLabel() !== '—';
    }

    public function dutyLocalStartLabel(): string
    {
        return $this->flight->dutyLocalStart ?: '—';
    }

    public function dutyLocalEndLabel(): string
    {
        return $this->flight->dutyLocalEnd ?: '—';
    }

    public function dutyLocalTimesLabel(): string
    {
        return $this->formatRangeLabel(
            $this->dutyLocalStartLabel(),
            $this->dutyLocalEndLabel(),
        );
    }

    // Airport Details
    public function hasAirportDetails(): bool
    {
        return $this->originAirportInfo() !== null
            || $this->destinationAirportInfo() !== null;
    }

    /**
     * @return array{
     *     iata: string,
     *     icao: string,
     *     name: string,
     *     city: ?string,
     *     state: ?string,
     *     country: ?string,
     *     location: ?string
     * }|null
     */
    public function originAirportInfo(): ?array
    {
        return $this->airportInfo(
            iata: $this->originIata(),
            icao: $this->originIcao(),
            name: $this->originName(),
            city: $this->originCity(),
            state: $this->originState(),
            country: $this->originCountry(),
        );
    }

    /**
     * @return array{
     *     iata: string,
     *     icao: string,
     *     name: string,
     *     city: ?string,
     *     state: ?string,
     *     country: ?string,
     *     location: ?string
     * }|null
     */
    public function destinationAirportInfo(): ?array
    {
        return $this->airportInfo(
            iata: $this->destinationIata(),
            icao: $this->destinationIcao(),
            name: $this->destinationName(),
            city: $this->destinationCity(),
            state: $this->destinationState(),
            country: $this->destinationCountry(),
        );
    }

    public function originIcao(): ?string
    {
        return $this->metadataString('origin_icao');
    }

    public function originName(): ?string
    {
        return $this->metadataString('origin_name');
    }

    public function originCity(): ?string
    {
        return $this->metadataString('origin_city');
    }

    public function originCountryCode(): ?string
    {
        return $this->metadataString('origin_country_code')
            ?? $this->metadataString('origin_country');
    }

    public function originState(): ?string
    {
        return $this->metadataString('origin_state');
    }

    public function originCountry(): ?string
    {
        return $this->metadataString('origin_country')
            ?? $this->metadataString('origin_country_code');
    }

    public function destinationIcao(): ?string
    {
        return $this->metadataString('destination_icao');
    }

    public function destinationName(): ?string
    {
        return $this->metadataString('destination_name');
    }

    public function destinationCity(): ?string
    {
        return $this->metadataString('destination_city');
    }

    public function destinationCountryCode(): ?string
    {
        return $this->metadataString('destination_country_code')
            ?? $this->metadataString('destination_country');
    }

    public function destinationState(): ?string
    {
        return $this->metadataString('destination_state');
    }

    public function destinationCountry(): ?string
    {
        return $this->metadataString('destination_country')
            ?? $this->metadataString('destination_country_code');
    }

    // Aircraft Details
    public function hasAircraftDetails(): bool
    {
        return $this->flight->aircraft !== null
            || $this->flight->tailNumber !== null;
    }

    public function aircraft(): ?string
    {
        return $this->flight->aircraft;
    }

    public function tailNumber(): ?string
    {
        return $this->flight->tailNumber;
    }

    public function airlineName(): ?string
    {
        return $this->metadataString('airline_name');
    }

    public function hasFooterContext(): bool
    {
        return $this->tailNumber() !== null || $this->airlineName() !== null;
    }

    public function footerContextLabel(): ?string
    {
        if ($this->tailNumber() !== null) {
            return 'Tail';
        }

        if ($this->airlineName() !== null) {
            return 'Airline';
        }

        return null;
    }

    public function footerContextValue(): ?string
    {
        return $this->tailNumber() ?? $this->airlineName();
    }

    // Crew Details
    public function crewMembers(): array
    {
        $crew = $this->flight->metadata['crew'] ?? [];

        return is_array($crew) ? $crew : [];
    }

    public function crewCount(): int
    {
        $metadataCount = $this->metadataInt('crew_count');

        if ($metadataCount !== null) {
            return $metadataCount;
        }

        return count($this->crewMembers());
    }

    public function deadheadingCrewCount(): int
    {
        $metadataCount = $this->metadataInt('deadheading_crew_count');

        if ($metadataCount !== null) {
            return $metadataCount;
        }

        return collect($this->crewMembers())
            ->where('deadheading', true)
            ->count();
    }

    public function operatingCrewCount(): int
    {
        return max(0, $this->crewCount() - $this->deadheadingCrewCount());
    }

    public function hasCrewDetails(): bool
    {
        return $this->crewCount() > 0 || ! empty($this->crewMembers());
    }

    // Metadata Helpers
    private function metadataInt(string $key): ?int
    {
        $value = $this->flight->metadata[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function formatTime(?string $value): string
    {
        return $value
            ? CarbonImmutable::parse($value)->format('g:i A')
            : '—';
    }

    private function formatCardTime(?string $value): string
    {
        return $value
            ? CarbonImmutable::parse($value)->setTimezone('UTC')->format('Hi \Z')
            : '—';
    }

    private function formatRangeLabel(string $start, string $end): string
    {
        if ($start === '—') {
            return $end;
        }

        if ($end === '—') {
            return $start;
        }

        return "{$start} - {$end}";
    }

    /**
     * @return array{
     *     iata: string,
     *     icao: string,
     *     name: string,
     *     city: ?string,
     *     state: ?string,
     *     country: ?string,
     *     location: ?string
     * }|null
     */
    private function airportInfo(
        string $iata,
        ?string $icao,
        ?string $name,
        ?string $city,
        ?string $state,
        ?string $country,
    ): ?array {
        if ($icao === null && $name === null && $city === null && $state === null && $country === null) {
            return null;
        }

        $location = collect([$city, $state, $country])
            ->filter(static fn (?string $value): bool => $value !== null && $value !== '')
            ->implode(', ');

        return [
            'iata' => $iata,
            'icao' => $icao ?? 'N/A',
            'name' => $name ?? 'Airport details unavailable.',
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'location' => $location !== '' ? $location : null,
        ];
    }

    private function metadataString(string $key): ?string
    {
        $value = $this->flight->metadata[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function downloadParseKey(): ?string
    {
        if ($this->flight->downloadUrl === '') {
            return null;
        }

        $query = parse_url($this->flight->downloadUrl, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $parameters);

        $parseKey = $parameters['parse_key'] ?? null;

        return is_string($parseKey) && $parseKey !== '' ? $parseKey : null;
    }
}
