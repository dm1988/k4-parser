<?php

namespace Tests\Feature;

use App\Filament\Resources\FlightEvents\Pages\CreateFlightEvent;
use App\Filament\Resources\FlightEvents\Pages\ListFlightEvents;
use App\Models\Aircraft;
use App\Models\FlightEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FlightEventResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_load_the_flight_events_table(): void
    {
        $this->actingAs(User::factory()->create());
        $events = FlightEvent::factory()->count(2)->create();

        Livewire::test(ListFlightEvents::class)
            ->assertCanSeeTableRecords($events)
            ->assertSee($events->first()->title)
            ->assertSee($events->last()->flight_number);
    }

    public function test_flight_events_table_can_search_records(): void
    {
        $this->actingAs(User::factory()->create());

        $firstEvent = FlightEvent::factory()->create([
            'title' => 'K4123 ANC-ICN',
            'flight_number' => 'K4123',
        ]);
        $secondEvent = FlightEvent::factory()->create([
            'title' => 'K4987 JFK-SEA',
            'flight_number' => 'K4987',
        ]);

        Livewire::test(ListFlightEvents::class)
            ->assertCanSeeTableRecords([$firstEvent, $secondEvent])
            ->searchTable('K4123')
            ->assertCanSeeTableRecords([$firstEvent])
            ->assertCanNotSeeTableRecords([$secondEvent]);
    }

    public function test_authenticated_users_can_create_flight_events_from_the_resource_form(): void
    {
        $this->actingAs(User::factory()->create());

        $aircraft = Aircraft::factory()->create([
            'tail_number' => 'N770CK',
        ]);

        Livewire::test(CreateFlightEvent::class)
            ->fillForm([
                'title' => 'K4770 CVG-HKG',
                'type' => 'flight',
                'status' => 'scheduled',
                'start' => '2026-07-01 08:00:00',
                'end' => '2026-07-01 14:30:00',
                'timezone' => 'UTC',
                'is_deadhead' => false,
                'flight_number' => 'K4770',
                'trip_id' => '7701',
                'origin' => 'CVG',
                'destination' => 'HKG',
                'aircraft_id' => $aircraft->getKey(),
                'tail_number' => 'N770CK',
                'type_label' => 'FLIGHT',
                'schedule_label' => 'CVG-HKG',
                'duration_label' => '6:30',
                'badge_color' => 'blue',
                'type_icon' => 'plane',
                'type_description' => 'Positioning flight for cargo departure.',
                'download_url' => 'https://example.com/downloads/7701',
                'download_id' => 'download-7701',
                'metadata' => [
                    'source' => 'test',
                    'station' => 'HKG',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('flight_events', [
            'title' => 'K4770 CVG-HKG',
            'flight_number' => 'K4770',
            'aircraft_id' => $aircraft->getKey(),
            'tail_number' => 'N770CK',
            'origin' => 'CVG',
            'destination' => 'HKG',
            'status' => 'scheduled',
        ]);
    }
}
