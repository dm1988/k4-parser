<?php

namespace Tests\Feature;

use App\Models\Airline;
use Database\Seeders\AirlineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirlineSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_valid_airlines_and_skips_malformed_codes(): void
    {
        $this->seed(AirlineSeeder::class);

        $this->assertDatabaseHas('airlines', [
            'name' => 'United Airlines',
            'iata_code' => 'UA',
            'icao_code' => 'UAL',
            'active' => true,
        ]);

        $this->assertDatabaseMissing('airlines', [
            'name' => 'Jayrow',
        ]);

        $this->assertNull(Airline::query()->where('name', 'Jayrow')->first());
    }
}
