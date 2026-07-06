<?php

namespace Tests\Feature;

use App\Filament\Resources\Aircraft\Pages\CreateAircraft;
use App\Filament\Resources\Aircraft\Pages\EditAircraft;
use App\Filament\Resources\Aircraft\Pages\ListAircraft;
use App\Models\Aircraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AircraftResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_search_aircraft_in_the_resource_table(): void
    {
        $this->actingAs($this->makeAdminUser());

        $firstAircraft = Aircraft::factory()->create([
            'tail_number' => 'N770CK',
        ]);
        $secondAircraft = Aircraft::factory()->create([
            'tail_number' => 'N771CK',
        ]);

        Livewire::test(ListAircraft::class)
            ->assertCanSeeTableRecords([$firstAircraft, $secondAircraft])
            ->searchTable('N770CK')
            ->assertCanSeeTableRecords([$firstAircraft])
            ->assertCanNotSeeTableRecords([$secondAircraft]);
    }

    public function test_admins_can_create_aircraft_from_the_resource_form(): void
    {
        $this->actingAs($this->makeAdminUser());

        Livewire::test(CreateAircraft::class)
            ->fillForm([
                'tail_number' => 'N999CK',
                'manufacturer' => 'Boeing',
                'type' => 'Boeing 777-F',
                'model' => '777-F',
                'is_active' => true,
                'airline' => 'Kalitta Air, LLC',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('aircraft', [
            'tail_number' => 'N999CK',
            'manufacturer' => 'Boeing',
            'model' => '777-F',
            'is_active' => true,
        ]);
    }

    public function test_admins_can_edit_aircraft_from_the_resource_form(): void
    {
        $this->actingAs($this->makeAdminUser());

        $aircraft = Aircraft::factory()->create([
            'tail_number' => 'N770CK',
            'model' => '777-F',
        ]);

        Livewire::test(EditAircraft::class, ['record' => $aircraft->getKey()])
            ->fillForm([
                'tail_number' => 'N770CK',
                'manufacturer' => 'Boeing',
                'type' => 'Boeing 777-300ERSF',
                'model' => '777-300ERSF',
                'is_active' => true,
                'airline' => 'Kalitta Air, LLC',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('aircraft', [
            'id' => $aircraft->getKey(),
            'type' => 'Boeing 777-300ERSF',
            'model' => '777-300ERSF',
        ]);
    }

    public function test_aircraft_form_requires_a_tail_number_and_active_state(): void
    {
        $this->actingAs($this->makeAdminUser());

        Livewire::test(CreateAircraft::class)
            ->fillForm([
                'tail_number' => null,
                'is_active' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'tail_number' => 'required',
                'is_active' => 'required',
            ]);
    }

    public function test_non_admin_users_can_not_access_aircraft_resource_pages(): void
    {
        $aircraft = Aircraft::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get('/admin/aircraft')->assertForbidden();
        $this->get('/admin/aircraft/create')->assertForbidden();
        $this->get("/admin/aircraft/{$aircraft->getKey()}/edit")->assertForbidden();
    }

    public function test_delete_actions_are_hidden_for_aircraft(): void
    {
        $this->actingAs($this->makeAdminUser());
        $aircraft = Aircraft::factory()->create();

        Livewire::test(ListAircraft::class)
            ->assertTableActionVisible('edit', $aircraft)
            ->assertTableBulkActionHidden('delete');

        Livewire::test(EditAircraft::class, ['record' => $aircraft->getKey()])
            ->assertActionHidden('delete');
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
