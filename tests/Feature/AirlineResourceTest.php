<?php

namespace Tests\Feature;

use App\Filament\Resources\Airlines\Pages\CreateAirline;
use App\Filament\Resources\Airlines\Pages\EditAirline;
use App\Filament\Resources\Airlines\Pages\ListAirlines;
use App\Models\Airline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AirlineResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_search_airlines_in_the_resource_table(): void
    {
        $this->actingAs($this->makeAdminUser());

        $firstAirline = Airline::query()->create([
            'name' => 'Kalitta Air',
            'iata_code' => 'K4',
            'icao_code' => 'CKS',
            'callsign' => 'CONNIE',
            'country' => 'US',
            'active' => true,
        ]);
        $secondAirline = Airline::query()->create([
            'name' => 'Atlas Air',
            'iata_code' => '5Y',
            'icao_code' => 'GTI',
            'callsign' => 'GIANT',
            'country' => 'US',
            'active' => true,
        ]);

        Livewire::test(ListAirlines::class)
            ->assertCanSeeTableRecords([$firstAirline, $secondAirline])
            ->searchTable('Kalitta')
            ->assertCanSeeTableRecords([$firstAirline])
            ->assertCanNotSeeTableRecords([$secondAirline]);
    }

    public function test_admins_can_create_airlines_from_the_resource_form(): void
    {
        $this->actingAs($this->makeAdminUser());

        Livewire::test(CreateAirline::class)
            ->fillForm([
                'name' => 'Atlas Air',
                'iata_code' => '5Y',
                'icao_code' => 'GTI',
                'callsign' => 'GIANT',
                'country' => 'US',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('airlines', [
            'name' => 'Atlas Air',
            'iata_code' => '5Y',
            'icao_code' => 'GTI',
            'active' => true,
        ]);
    }

    public function test_admins_can_edit_airlines_from_the_resource_form(): void
    {
        $this->actingAs($this->makeAdminUser());

        $airline = Airline::query()->create([
            'name' => 'Kalitta Air',
            'iata_code' => 'K4',
            'icao_code' => 'CKS',
            'callsign' => 'CONNIE',
            'country' => 'US',
            'active' => true,
        ]);

        Livewire::test(EditAirline::class, ['record' => $airline->getKey()])
            ->fillForm([
                'name' => 'Kalitta Charters',
                'iata_code' => 'K4',
                'icao_code' => 'CKS',
                'callsign' => 'CONNIE',
                'country' => 'US',
                'active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('airlines', [
            'id' => $airline->getKey(),
            'name' => 'Kalitta Charters',
        ]);
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
