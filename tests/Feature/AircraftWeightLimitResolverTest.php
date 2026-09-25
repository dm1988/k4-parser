<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Services\FlightPlan\AircraftWeightLimitResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AircraftWeightLimitResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_positive_pound_limits_by_normalized_tail_number(): void
    {
        Aircraft::factory()->create([
            'tail_number' => 'N774CK',
            'max_ramp_weight' => 768000,
            'max_zero_fuel_weight' => 547000,
            'max_takeoff_weight' => 766000,
            'max_landing_weight' => 575000,
        ]);

        $limits = app(AircraftWeightLimitResolver::class)->resolve(' n774ck ');

        $this->assertSame(768000, $limits->ramp?->amount);
        $this->assertSame(547000, $limits->zeroFuel?->amount);
        $this->assertSame(766000, $limits->takeoff?->amount);
        $this->assertSame(575000, $limits->landing?->amount);
        $this->assertSame('lb', $limits->ramp?->unit);
    }

    public function test_it_returns_unavailable_limits_for_missing_aircraft_and_non_positive_values(): void
    {
        Aircraft::factory()->create([
            'tail_number' => 'N774CK',
            'max_ramp_weight' => 0,
            'max_zero_fuel_weight' => null,
            'max_takeoff_weight' => null,
            'max_landing_weight' => null,
        ]);

        $missingAircraft = app(AircraftWeightLimitResolver::class)->resolve('N999ZZ');
        $emptyTailNumber = app(AircraftWeightLimitResolver::class)->resolve(null);
        $invalidLimits = app(AircraftWeightLimitResolver::class)->resolve('N774CK');

        $this->assertNull($missingAircraft->ramp);
        $this->assertNull($emptyTailNumber->zeroFuel);
        $this->assertNull($invalidLimits->ramp);
        $this->assertNull($invalidLimits->zeroFuel);
        $this->assertNull($invalidLimits->takeoff);
        $this->assertNull($invalidLimits->landing);
    }
}
