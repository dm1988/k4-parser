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

    // Airport Details
    public function hasAirportDetails(): bool
    {
        return $this->originIcao() !== null
            || $this->originName() !== null
            || $this->originCity() !== null
            || $this->destinationIcao() !== null
            || $this->destinationName() !== null
            || $this->destinationCity() !== null;
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
        return $this->metadataString('origin_country_code');
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
        return $this->metadataString('destination_country_code');
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

    private function metadataString(string $key): ?string
    {
        $value = $this->flight->metadata[$key] ?? null;

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
