<?php

namespace Tests\Feature;

use App\Filament\Resources\FlightEvents\Pages\ListFlightEvents;
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
