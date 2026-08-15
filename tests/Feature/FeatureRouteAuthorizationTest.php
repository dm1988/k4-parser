<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FeatureRouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_schedule_extractor_routes_return_not_found_before_validation(): void
    {
        Config::set('features.schedule_extractor.enabled', false);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('parse.export'))
            ->assertNotFound();

        $this->get(route('parse.export.event.duty', ['eventId' => 'event123']))
            ->assertNotFound();
    }

    public function test_schedule_extractor_routes_forbid_users_without_the_required_capability(): void
    {
        Config::set('features.schedule_extractor.for_all_users', false);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('parse.export'))
            ->assertForbidden();
    }

    public function test_duty_export_route_uses_its_more_specific_capability(): void
    {
        Config::set('features.schedule_extractor.for_all_users', true);
        Config::set('features.schedule_extractor.duty_export_for_all_users', false);

        $this->actingAs(User::factory()->create())
            ->get(route('parse.export.event.duty', ['eventId' => 'event123']))
            ->assertForbidden();
    }

    public function test_parser_pages_keep_their_existing_unavailable_state(): void
    {
        Config::set('features.schedule_extractor.enabled', false);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Schedule extractor access is currently unavailable.');

        $this->get(route('parse.index'))
            ->assertOk()
            ->assertSeeText('Schedule extractor access is currently unavailable.');
    }

    public function test_flight_release_routes_enforce_feature_and_capability_before_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('flight-release.store'))
            ->assertForbidden();

        Config::set('features.flight_release.enabled', false);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('flight-release.store'))
            ->assertNotFound();
    }

    public function test_unverified_users_are_redirected_before_demo_routes_are_authorized(): void
    {
        Config::set('features.flight_release.enabled', true);
        Config::set('features.flight_release.for_all_users', true);

        $unverifiedUser = User::factory()->unverified()->create();

        $this->actingAs($unverifiedUser)
            ->get(route('flight-release.index'))
            ->assertRedirect(route('verification.notice'));

        $this->assertFalse($unverifiedUser->canUseFlightRelease());
    }
}
