<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\FlightEvent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModelConventionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_aircraft_casts_scopes_accessor_and_relationship_are_typed_and_functional(): void
    {
        $matchingAircraft = Aircraft::factory()->create([
            'tail_number' => 'N773CK',
            'airline' => 'Kalitta Air',
            'type' => 'Boeing 777-F',
            'model' => '777-F',
            'is_active' => true,
        ]);
        Aircraft::factory()->create([
            'tail_number' => 'N774CK',
            'airline' => 'Other Airline',
            'is_active' => false,
        ]);

        $result = Aircraft::query()
            ->active()
            ->byAirline('Kalitta Air')
            ->byType('Boeing 777-F')
            ->byModel('777-F')
            ->byTailNumber('N773CK')
            ->sole();

        $this->assertTrue($result->is_active);
        $this->assertSame('N773CK', $result->display_name);
        $this->assertTrue($matchingAircraft->flightEvents() instanceof HasMany);
    }

    public function test_airline_cast_and_code_scope_are_normalized(): void
    {
        $matchingAirline = Airline::query()->create([
            'name' => 'Kalitta Air',
            'iata_code' => 'K4',
            'icao_code' => 'CKS',
            'active' => true,
        ]);
        Airline::query()->create([
            'name' => 'Inactive Airline',
            'iata_code' => 'ZZ',
            'icao_code' => 'ZZZ',
            'active' => false,
        ]);

        $result = Airline::query()->where('active', true)->byCode(' cks ')->sole();

        $this->assertTrue($result->active);
        $this->assertTrue($matchingAirline->is($result));
    }

    public function test_flight_event_casts_scopes_accessor_and_relationship_are_typed_and_functional(): void
    {
        $matchingEvent = FlightEvent::factory()->withoutAircraft()->create([
            'tail_number' => 'N773CK',
            'origin' => 'ANC',
            'destination' => 'ICN',
            'start' => '2026-07-01 10:00:00',
            'end' => '2026-07-01 12:30:00',
            'is_deadhead' => true,
            'metadata' => ['source' => 'test'],
        ]);
        FlightEvent::factory()->withoutAircraft()->create([
            'tail_number' => 'N774CK',
            'origin' => 'JFK',
            'destination' => 'LAX',
            'start' => '2026-08-01 10:00:00',
            'end' => '2026-08-01 12:00:00',
        ]);

        $result = FlightEvent::query()
            ->byTailNumber('N773CK')
            ->byOrigin('ANC')
            ->byDestination('ICN')
            ->byDateRange('2026-07-01 00:00:00', '2026-07-01 23:59:59')
            ->byTimeRange(
                Carbon::parse('2026-07-01 09:00:00'),
                Carbon::parse('2026-07-01 13:00:00'),
            )
            ->sole();

        $this->assertTrue($matchingEvent->is($result));
        $this->assertInstanceOf(Carbon::class, $result->start);
        $this->assertInstanceOf(Carbon::class, $result->end);
        $this->assertTrue($result->is_deadhead);
        $this->assertSame(['source' => 'test'], $result->metadata);
        $this->assertSame('2:30', $result->duration);
        $this->assertTrue($matchingEvent->aircraft() instanceof BelongsTo);
    }

    public function test_aircraft_and_flight_event_relationships_are_true_inverses(): void
    {
        $aircraft = Aircraft::factory()->create([
            'tail_number' => 'N773CK',
        ]);
        $relatedEvent = FlightEvent::factory()->forAircraft($aircraft)->create();
        $fallbackEvent = FlightEvent::factory()->withoutAircraft()->create([
            'tail_number' => 'N773CK',
        ]);

        $this->assertTrue($aircraft->is($relatedEvent->aircraft));
        $this->assertTrue($aircraft->flightEvents->contains($relatedEvent));
        $this->assertFalse($aircraft->flightEvents->contains($fallbackEvent));
        $this->assertNull($relatedEvent->getRawOriginal('tail_number'));
        $this->assertSame('N773CK', $relatedEvent->display_tail_number);
        $this->assertSame('N773CK', $fallbackEvent->display_tail_number);
    }

    public function test_deleting_aircraft_nulls_the_flight_event_relationship(): void
    {
        $aircraft = Aircraft::factory()->create();
        $flightEvent = FlightEvent::factory()->forAircraft($aircraft)->create();

        $aircraft->delete();

        $this->assertModelExists($flightEvent);
        $this->assertNull($flightEvent->fresh()->aircraft_id);
    }

    public function test_flight_event_query_columns_are_indexed(): void
    {
        $indexes = collect(Schema::getIndexes('flight_events'))
            ->mapWithKeys(fn (array $index): array => [$index['name'] => $index['columns']]);

        $this->assertSame(['start'], $indexes->get('flight_events_start_index'));
        $this->assertSame(['aircraft_id'], $indexes->get('flight_events_aircraft_id_index'));
        $this->assertSame(
            ['tail_number', 'start', 'end'],
            $indexes->get('flight_events_tail_number_start_end_index'),
        );
    }

    public function test_flight_event_defaults_match_the_database_defaults(): void
    {
        $this->assertFalse((new FlightEvent)->is_deadhead);
    }
}
