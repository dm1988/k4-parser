<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use Database\Seeders\AircraftWeightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AircraftWeightSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_idempotently_imports_aircraft_weights_without_overwriting_unrelated_data(): void
    {
        $existingAircraft = Aircraft::factory()->create([
            'tail_number' => 'N701CK',
            'manufacturer' => 'Existing Manufacturer',
            'max_ramp_weight' => 999999,
            'max_zero_fuel_weight' => 1,
            'max_takeoff_weight' => 1,
            'max_landing_weight' => 1,
            'minimum_flight_weight' => 1,
        ]);

        $aircraftWithoutSourceEngine = Aircraft::factory()->create([
            'tail_number' => 'N700CK',
            'engines' => 'Existing Engine',
        ]);

        $this->seed(AircraftWeightSeeder::class);

        $existingAircraftId = $existingAircraft->getKey();
        $aircraftWithoutSourceEngineId = $aircraftWithoutSourceEngine->getKey();

        $this->seed(AircraftWeightSeeder::class);

        $this->assertDatabaseCount('aircraft', 37);
        $this->assertDatabaseHas('aircraft', [
            'id' => $existingAircraftId,
            'tail_number' => 'N701CK',
            'manufacturer' => 'Existing Manufacturer',
            'max_ramp_weight' => 999999,
            'max_zero_fuel_weight' => 610000,
            'max_takeoff_weight' => 870000,
            'max_landing_weight' => 652000,
            'minimum_flight_weight' => 352293,
            'engines' => 'PW4056-3',
        ]);
        $this->assertDatabaseHas('aircraft', [
            'id' => $aircraftWithoutSourceEngineId,
            'tail_number' => 'N700CK',
            'max_zero_fuel_weight' => 635000,
            'max_takeoff_weight' => 875000,
            'max_landing_weight' => 666000,
            'minimum_flight_weight' => 353313,
            'engines' => 'Existing Engine',
        ]);
        $this->assertDatabaseHas('aircraft', [
            'tail_number' => 'N769CK',
            'model' => '777-300ERSF',
            'max_zero_fuel_weight' => 558000,
            'max_takeoff_weight' => 775000,
            'max_landing_weight' => 583000,
            'minimum_flight_weight' => 336958,
            'engines' => 'GE90-115B',
        ]);
        $this->assertDatabaseHas('aircraft', [
            'tail_number' => 'N795CK',
            'model' => '777-F',
            'max_zero_fuel_weight' => 547000,
            'max_takeoff_weight' => 766000,
            'max_landing_weight' => 575000,
            'minimum_flight_weight' => 311943,
            'engines' => 'GE90-110B1',
        ]);

        $this->assertSame(1, Aircraft::query()->where('tail_number', 'N701CK')->count());
        $this->assertSame(1, Aircraft::query()->where('tail_number', 'N700CK')->count());
    }
}
