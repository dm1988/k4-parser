<?php

namespace Database\Seeders;

use App\Models\Airline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AirlineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/airlines.dat');

        if (! file_exists($path)) {
            $this->command?->error("File not found: {$path}");

            return;
        }

        DB::transaction(function () use ($path) {
            $handle = fopen($path, 'r');
            $skippedInvalidRows = 0;

            while (($row = fgetcsv($handle)) !== false) {
                /**
                 * OpenFlights format:
                 *
                 * 0  Airline ID
                 * 1  Name
                 * 2  Alias
                 * 3  IATA
                 * 4  ICAO
                 * 5  Callsign
                 * 6  Country
                 * 7  Active
                 */
                $iata = $this->normalizeIataCode($row[3] ?? null);
                $icao = $this->normalizeIcaoCode($row[4] ?? null);

                // Skip records with neither code.
                if (! $iata && ! $icao) {
                    $skippedInvalidRows++;

                    continue;
                }

                Airline::updateOrCreate(
                    $icao !== null
                        ? ['icao_code' => $icao]
                        : ['iata_code' => $iata],
                    [
                        'name' => $row[1] ?? null,
                        'iata_code' => $iata,
                        'icao_code' => $icao,
                        'callsign' => $this->normalizeTextValue($row[5] ?? null),
                        'country' => $this->normalizeTextValue($row[6] ?? null),
                        'active' => ($row[7] ?? 'N') === 'Y',
                    ]
                );
            }

            fclose($handle);

            if ($skippedInvalidRows > 0) {
                $this->command?->warn("Skipped {$skippedInvalidRows} airline rows with invalid IATA/ICAO codes.");
            }
        });

        $this->command?->info('Airlines imported successfully.');
    }

    private function normalizeIataCode(?string $value): ?string
    {
        $value = $this->normalizeValue($value);

        if ($value === null || preg_match('/^[A-Z0-9]{2,3}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function normalizeIcaoCode(?string $value): ?string
    {
        $value = $this->normalizeValue($value);

        if ($value === null || preg_match('/^[A-Z0-9]{3,4}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function normalizeValue(?string $value): ?string
    {
        if (! $value || $value === '\\N') {
            return null;
        }

        $value = strtoupper(trim($value));

        return $value === '' ? null : $value;
    }

    private function normalizeTextValue(?string $value): ?string
    {
        if (! $value || $value === '\\N') {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
