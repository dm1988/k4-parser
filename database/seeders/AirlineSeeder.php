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

            while (($row = fgetcsv($handle, escape: '')) !== false) {
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
                $iata = $this->normalizeCode($row[3] ?? null, 2, 3);
                $icao = $this->normalizeCode($row[4] ?? null, 3, 4);

                // Skip records with neither code.
                if (! $iata && ! $icao) {
                    continue;
                }

                Airline::updateOrCreate(
                    $icao ? ['icao_code' => $icao] : ['iata_code' => $iata],
                    [
                        'name' => $row[1] ?? null,
                        'iata_code' => $iata,
                        'icao_code' => $icao,
                        'callsign' => $this->nullify($row[5] ?? null),
                        'country' => $this->nullify($row[6] ?? null),
                        'active' => ($row[7] ?? 'N') === 'Y',
                    ]
                );
            }

            fclose($handle);
        });

        $this->command?->info('Airlines imported successfully.');
    }

    private function nullify(?string $value): ?string
    {
        if (! $value || $value === '\\N') {
            return null;
        }

        return trim($value);
    }

    private function normalizeCode(?string $value, int $minLength, int $maxLength): ?string
    {
        $value = $this->nullify($value);

        if ($value === null) {
            return null;
        }

        $value = strtoupper($value);

        return preg_match("/^[A-Z0-9]{{$minLength},{$maxLength}}$/", $value)
            ? $value
            : null;
    }
}
