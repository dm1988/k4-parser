<?php

namespace Database\Seeders;

use App\Models\Aircraft;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class AircraftWeightSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/k4 aircraft data.txt');

        if (! file_exists($path)) {
            $this->command->error("File not found: {$path}");

            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            $this->command->error("Unable to read file: {$path}");

            return;
        }

        $aircraftData = $this->parseAircraftData($lines);

        DB::transaction(function () use ($aircraftData): void {
            foreach ($aircraftData as $tailNumber => $data) {
                $aircraft = Aircraft::query()->firstOrNew([
                    'tail_number' => $tailNumber,
                ]);

                if (! $aircraft->exists) {
                    $aircraft->fill([
                        ...$this->aircraftDetails($data['fleet_type']),
                        'is_active' => true,
                        'airline' => 'Kalitta Air, LLC',
                    ]);
                }

                $aircraft->fill([
                    'max_zero_fuel_weight' => $data['max_zero_fuel_weight'],
                    'max_takeoff_weight' => $data['max_takeoff_weight'],
                    'max_landing_weight' => $data['max_landing_weight'],
                    'minimum_flight_weight' => $data['minimum_flight_weight'],
                ]);

                if ($data['engines'] !== null) {
                    $aircraft->engines = $data['engines'];
                }

                $aircraft->save();
            }
        });

        $this->command->info(sprintf('Imported weights for %d aircraft.', count($aircraftData)));
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, array{
     *     fleet_type: string,
     *     max_zero_fuel_weight: int|null,
     *     max_takeoff_weight: int|null,
     *     max_landing_weight: int|null,
     *     minimum_flight_weight: int|null,
     *     engines: string|null
     * }>
     */
    private function parseAircraftData(array $lines): array
    {
        $aircraftData = [];
        $tailNumber = null;

        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B\"");

            if (preg_match('/^(N\d{3}CK)\s+(74Y|77V|77X)\s+K4\b/', $line, $matches) === 1) {
                $tailNumber = $matches[1];
                $aircraftData[$tailNumber] = [
                    'fleet_type' => $matches[2],
                    'max_zero_fuel_weight' => null,
                    'max_takeoff_weight' => null,
                    'max_landing_weight' => null,
                    'minimum_flight_weight' => null,
                    'engines' => null,
                ];

                continue;
            }

            if ($tailNumber === null) {
                continue;
            }

            if (preg_match('/^(MZFW|MTOW|MLW|OEW|Engines):\s*(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            $field = match ($matches[1]) {
                'MZFW' => 'max_zero_fuel_weight',
                'MTOW' => 'max_takeoff_weight',
                'MLW' => 'max_landing_weight',
                'OEW' => 'minimum_flight_weight',
                'Engines' => 'engines',
            };
            $value = trim($matches[2]);

            $aircraftData[$tailNumber][$field] = $field === 'engines'
                ? ($value !== '' ? $value : null)
                : (ctype_digit($value) ? (int) $value : null);
        }

        return $aircraftData;
    }

    /**
     * @return array{manufacturer: string, type: string, model: string}
     */
    private function aircraftDetails(string $fleetType): array
    {
        return match ($fleetType) {
            '74Y' => [
                'manufacturer' => 'Boeing',
                'type' => 'Boeing 747-400F',
                'model' => '747-400F',
            ],
            '77V' => [
                'manufacturer' => 'Boeing',
                'type' => 'Boeing 777-300ERSF',
                'model' => '777-300ERSF',
            ],
            '77X' => [
                'manufacturer' => 'Boeing',
                'type' => 'Boeing 777-F',
                'model' => '777-F',
            ],
            default => throw new UnexpectedValueException("Unsupported fleet type: {$fleetType}"),
        };
    }
}
