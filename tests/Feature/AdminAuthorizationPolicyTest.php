<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\FlightEvent;
use App\Models\ParseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_verified_admins_can_access_the_filament_admin_panel(): void
    {
        $panel = filament()->getPanel('admin');

        $admin = $this->makeAdminUser();
        $normalUser = $this->makeNormalUser();
        $inactiveAdmin = $this->makeAdminUser(isActive: false);
        $unverifiedAdmin = $this->makeAdminUser(verified: false);

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertFalse($normalUser->canAccessPanel($panel));
        $this->assertFalse($inactiveAdmin->canAccessPanel($panel));
        $this->assertFalse($unverifiedAdmin->canAccessPanel($panel));
    }

    public function test_aircraft_policy_allows_admin_view_create_update_but_not_delete(): void
    {
        $admin = $this->makeAdminUser();
        $aircraft = Aircraft::factory()->create();

        $this->assertTrue($admin->can('viewAny', Aircraft::class));
        $this->assertTrue($admin->can('view', $aircraft));
        $this->assertTrue($admin->can('create', Aircraft::class));
        $this->assertTrue($admin->can('update', $aircraft));
        $this->assertFalse($admin->can('delete', $aircraft));
        $this->assertFalse($admin->can('deleteAny', Aircraft::class));
    }

    public function test_airline_policy_allows_admin_view_create_update_but_not_delete(): void
    {
        $admin = $this->makeAdminUser();
        $airline = Airline::query()->create([
            'name' => 'Kalitta Air',
            'iata_code' => 'K4',
            'icao_code' => 'CKS',
            'callsign' => 'CONNIE',
            'country' => 'US',
            'active' => true,
        ]);

        $this->assertTrue($admin->can('viewAny', Airline::class));
        $this->assertTrue($admin->can('view', $airline));
        $this->assertTrue($admin->can('create', Airline::class));
        $this->assertTrue($admin->can('update', $airline));
        $this->assertFalse($admin->can('delete', $airline));
        $this->assertFalse($admin->can('deleteAny', Airline::class));
    }

    public function test_flight_event_policy_allows_admin_view_and_delete_only(): void
    {
        $admin = $this->makeAdminUser();
        $flightEvent = FlightEvent::factory()->create();

        $this->assertTrue($admin->can('viewAny', FlightEvent::class));
        $this->assertTrue($admin->can('view', $flightEvent));
        $this->assertFalse($admin->can('create', FlightEvent::class));
        $this->assertFalse($admin->can('update', $flightEvent));
        $this->assertTrue($admin->can('delete', $flightEvent));
        $this->assertTrue($admin->can('deleteAny', FlightEvent::class));
    }

    public function test_parse_request_policy_allows_admin_view_and_delete_only(): void
    {
        $admin = $this->makeAdminUser();
        $parseRequest = ParseRequest::query()->create([
            'user_id' => $admin->getKey(),
            'request_uuid' => '89f6a72f-16d1-499f-95ea-7fc13c5913ef',
            'source_type' => 'pasted_text',
            'parser_type' => 'roster',
            'status' => 'success',
            'parse_duration_ms' => 55,
            'detected_event_count' => 1,
            'detected_flight_count' => 1,
            'detected_hotel_count' => 0,
            'app_version' => '1.0.0',
            'parser_version' => '2026.06',
        ]);

        $this->assertTrue($admin->can('viewAny', ParseRequest::class));
        $this->assertTrue($admin->can('view', $parseRequest));
        $this->assertFalse($admin->can('create', ParseRequest::class));
        $this->assertFalse($admin->can('update', $parseRequest));
        $this->assertTrue($admin->can('delete', $parseRequest));
        $this->assertTrue($admin->can('deleteAny', ParseRequest::class));
    }

    public function test_user_policy_allows_admin_view_and_update_but_not_create_or_delete(): void
    {
        $admin = $this->makeAdminUser();
        $targetUser = $this->makeNormalUser();

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('view', $targetUser));
        $this->assertFalse($admin->can('create', User::class));
        $this->assertTrue($admin->can('update', $targetUser));
        $this->assertFalse($admin->can('delete', $targetUser));
        $this->assertFalse($admin->can('deleteAny', User::class));
    }

    public function test_non_admin_users_are_denied_all_resource_policy_abilities(): void
    {
        $user = $this->makeNormalUser();
        $aircraft = Aircraft::factory()->create();
        $airline = Airline::query()->create(['name' => 'Kalitta Air']);
        $flightEvent = FlightEvent::factory()->create();
        $parseRequest = ParseRequest::query()->create([
            'user_id' => $user->getKey(),
            'request_uuid' => fake()->uuid(),
            'source_type' => 'pasted_text',
            'parser_type' => 'roster',
            'status' => 'success',
            'parse_duration_ms' => 55,
        ]);

        foreach ([
            Aircraft::class => $aircraft,
            Airline::class => $airline,
            FlightEvent::class => $flightEvent,
            ParseRequest::class => $parseRequest,
            User::class => $this->makeNormalUser(),
        ] as $modelClass => $model) {
            $this->assertFalse($user->can('viewAny', $modelClass));
            $this->assertFalse($user->can('view', $model));
            $this->assertFalse($user->can('create', $modelClass));
            $this->assertFalse($user->can('update', $model));
            $this->assertFalse($user->can('delete', $model));
            $this->assertFalse($user->can('deleteAny', $modelClass));
        }
    }

    public function test_guest_and_ineligible_admins_can_not_open_the_admin_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->actingAs($this->makeAdminUser(isActive: false));
        $this->get('/admin')->assertForbidden();

        $this->actingAs($this->makeAdminUser(verified: false));
        $this->get('/admin')->assertForbidden();
    }

    public function test_restore_force_delete_and_reorder_policy_abilities_match_resource_rules(): void
    {
        $admin = $this->makeAdminUser();
        $aircraft = Aircraft::factory()->create();
        $airline = Airline::query()->create(['name' => 'Kalitta Air']);
        $flightEvent = FlightEvent::factory()->create();
        $parseRequest = ParseRequest::query()->create([
            'user_id' => $admin->getKey(),
            'request_uuid' => fake()->uuid(),
            'source_type' => 'pasted_text',
            'parser_type' => 'roster',
            'status' => 'success',
            'parse_duration_ms' => 55,
        ]);

        foreach ([$aircraft, $airline, $flightEvent, $parseRequest, $this->makeNormalUser()] as $model) {
            $this->assertFalse($admin->can('restore', $model));
            $this->assertFalse($admin->can('forceDelete', $model));
        }

        $this->assertTrue($admin->can('reorder', Aircraft::class));
        $this->assertTrue($admin->can('reorder', Airline::class));
        $this->assertFalse($admin->can('reorder', FlightEvent::class));
        $this->assertFalse($admin->can('reorder', ParseRequest::class));
    }

    private function makeAdminUser(bool $verified = true, bool $isActive = true): User
    {
        $user = User::factory()->create([
            'email_verified_at' => $verified ? now() : null,
        ]);

        $user->forceFill([
            'role' => 'admin',
            'is_active' => $isActive,
        ])->save();

        return $user->refresh();
    }

    private function makeNormalUser(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'role' => 'user',
            'is_active' => true,
        ])->save();

        return $user->refresh();
    }
}
