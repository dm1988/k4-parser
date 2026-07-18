<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\FlightEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentDevelopmentGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_eloquent_strictness_is_enabled_outside_production(): void
    {
        $this->assertFalse(app()->isProduction());
        $this->assertTrue(Model::preventsLazyLoading());
        $this->assertTrue(Model::preventsSilentlyDiscardingAttributes());
        $this->assertTrue(Model::preventsAccessingMissingAttributes());
    }

    public function test_lazy_loading_relationships_throws_an_exception(): void
    {
        $aircraft = Aircraft::factory()->create();
        FlightEvent::factory()->count(2)->forAircraft($aircraft)->create();
        $flightEvents = FlightEvent::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $flightEvents->firstOrFail()->aircraft;
    }
}
