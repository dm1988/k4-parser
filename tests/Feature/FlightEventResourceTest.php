<?php

namespace Tests\Feature;

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

    public function test_admins_can_load_the_flight_events_table(): void
    {
        $this->actingAs($this->makeAdminUser());
        $events = FlightEvent::factory()
            ->withoutAircraft()
            ->count(2)
            ->create();

        Livewire::test(ListFlightEvents::class)
            ->assertCanSeeTableRecords($events)
            ->assertSee($events->first()->title)
            ->assertSee($events->last()->flight_number);
    }

    public function test_admins_can_search_flight_events_table_records(): void
    {
        $this->actingAs($this->makeAdminUser());

        $firstEvent = FlightEvent::factory()->withoutAircraft()->create([
            'title' => 'K4123 ANC-ICN',
            'flight_number' => 'K4123',
            'tail_number' => 'N770CK',
        ]);
        $secondEvent = FlightEvent::factory()->withoutAircraft()->create([
            'title' => 'K4987 JFK-SEA',
            'flight_number' => 'K4987',
            'tail_number' => 'N771CK',
        ]);

        Livewire::test(ListFlightEvents::class)
            ->assertCanSeeTableRecords([$firstEvent, $secondEvent])
            ->searchTable('K4123')
            ->assertCanSeeTableRecords([$firstEvent])
            ->assertCanNotSeeTableRecords([$secondEvent]);
    }

    public function test_non_admin_users_can_not_access_the_flight_events_table(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/flight-events')->assertForbidden();
    }

    public function test_admins_can_filter_flight_events_by_type_status_aircraft_and_deadhead_state(): void
    {
        $this->actingAs($this->makeAdminUser());
        $aircraft = Aircraft::factory()->create();
        $matchingEvent = FlightEvent::factory()->forAircraft($aircraft)->deadhead()->create([
            'status' => 'delayed',
        ]);
        $otherEvent = FlightEvent::factory()->withoutAircraft()->create([
            'type' => 'flight',
            'status' => 'scheduled',
            'is_deadhead' => false,
        ]);

        Livewire::test(ListFlightEvents::class)
            ->filterTable('type', 'deadhead')
            ->filterTable('status', 'delayed')
            ->filterTable('aircraft', $aircraft)
            ->filterTable('is_deadhead', true)
            ->assertCanSeeTableRecords([$matchingEvent])
            ->assertCanNotSeeTableRecords([$otherEvent]);
    }

    public function test_flight_events_are_sorted_by_start_descending_by_default(): void
    {
        $this->actingAs($this->makeAdminUser());
        $olderEvent = FlightEvent::factory()->withoutAircraft()->create([
            'start' => '2026-07-01 12:00:00',
            'end' => '2026-07-01 14:00:00',
        ]);
        $newerEvent = FlightEvent::factory()->withoutAircraft()->create([
            'start' => '2026-07-02 12:00:00',
            'end' => '2026-07-02 14:00:00',
        ]);

        Livewire::test(ListFlightEvents::class)
            ->assertCanSeeTableRecords([$newerEvent, $olderEvent], inOrder: true);
    }

    public function test_flight_events_have_no_individual_delete_action_but_can_be_deleted_in_bulk(): void
    {
        $this->actingAs($this->makeAdminUser());
        $bulkEvents = FlightEvent::factory()->withoutAircraft()->count(2)->create();

        Livewire::test(ListFlightEvents::class)
            ->assertTableActionDoesNotExist('delete')
            ->callTableBulkAction('delete', $bulkEvents);

        $bulkEvents->each(fn (FlightEvent $flightEvent) => $this->assertModelMissing($flightEvent));
    }

    public function test_create_and_edit_pages_are_forbidden_by_the_flight_event_policy(): void
    {
        $this->actingAs($this->makeAdminUser());
        $event = FlightEvent::factory()->withoutAircraft()->create();

        $this->get('/admin/flight-events/create')->assertForbidden();
        $this->get("/admin/flight-events/{$event->getKey()}/edit")->assertForbidden();

        Livewire::test(ListFlightEvents::class)
            ->assertTableActionHidden('edit', $event);
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        return $user->refresh();
    }
}
