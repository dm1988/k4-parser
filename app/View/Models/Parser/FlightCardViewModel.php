<?php

namespace App\View\Models\Parser;

use App\DTOs\Flight;
use App\Enums\ParserEventType;
use Carbon\CarbonImmutable;

readonly class FlightCardViewModel
{
    public function __construct(
        public Flight $flight,
    ) {
    }

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
