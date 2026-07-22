<?php

namespace App\DTOs;

class AirportData
{
    public function __construct(
        public string $icao,
        public string $iata,
        public string $name,
        public string $city,
        public ?string $state, // State can sometimes be null globally
        public string $country,
    ) {}

    /**
     * Instantiates the DTO from the provider's specific API response array structure.
     */
    public static function fromApi(array $data): self
    {
        return new self(
            icao: self::stringValue($data['icao'] ?? null),
            iata: self::stringValue($data['iata'] ?? null),
            name: self::stringValue($data['name'] ?? null),
            city: self::stringValue($data['city'] ?? null),
            state: self::nullableStringValue($data['state'] ?? null),
            country: self::stringValue($data['country'] ?? null),
        );
    }

    public function isUsable(): bool
    {
        return $this->icao !== ''
            || $this->iata !== '';
    }

    /**
     * Restore the DTO from application-controlled cached data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            icao: self::stringValue($data['icao'] ?? null),
            iata: self::stringValue($data['iata'] ?? null),
            name: self::stringValue($data['name'] ?? null),
            city: self::stringValue($data['city'] ?? null),
            state: self::nullableStringValue($data['state'] ?? null),
            country: self::stringValue($data['country'] ?? null),
        );
    }

    /**
     * @return array{
     *     icao: string,
     *     iata: string,
     *     name: string,
     *     city: string,
     *     state: string|null,
     *     country: string
     * }
     */
    public function toArray(): array
    {
        return [
            'icao' => $this->icao,
            'iata' => $this->iata,
            'name' => $this->name,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
        ];
    }

    protected static function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    protected static function nullableStringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::stringValue($value);
    }
}
