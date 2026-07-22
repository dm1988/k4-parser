<?php

namespace App\Services\Clients;

use App\Models\Airline;
use Illuminate\Database\QueryException;

class AirlineCodeLookupClient
{
    /**
     * @var array<string, string>|null
     */
    private ?array $airlinesByIataCode = null;

    public function airlineNameForIataCode(string $code): ?string
    {
        $normalizedCode = strtoupper(trim($code));

        if ($normalizedCode === '') {
            return null;
        }

        try {
            $databaseMatch = Airline::query()
                ->where('iata_code', $normalizedCode)
                ->value('name');
        } catch (QueryException) {
            $databaseMatch = null;
        }

        if (is_string($databaseMatch) && trim($databaseMatch) !== '') {
            return trim($databaseMatch);
        }

        return $this->airlinesByIataCode()[$normalizedCode] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function airlinesByIataCode(): array
    {
        if ($this->airlinesByIataCode !== null) {
            return $this->airlinesByIataCode;
        }

        $path = database_path('seeders/data/airlines.dat');

        if (! file_exists($path)) {
            return $this->airlinesByIataCode = [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return $this->airlinesByIataCode = [];
        }

        $airlines = [];

        while (($row = fgetcsv($handle)) !== false) {
            $iataCode = $this->normalizeValue($row[3] ?? null);
            $name = $this->normalizeValue($row[1] ?? null);

            if ($iataCode === null || $name === null || isset($airlines[$iataCode])) {
                continue;
            }

            $airlines[$iataCode] = $name;
        }

        fclose($handle);

        return $this->airlinesByIataCode = $airlines;
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmedValue = trim($value);

        if ($trimmedValue === '' || $trimmedValue === '\\N') {
            return null;
        }

        return $trimmedValue;
    }
}
