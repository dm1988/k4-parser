<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FlightReleaseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_view_the_flight_plan_brief_page(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('flight-release.index'));

        $response->assertOk();
        $response->assertSee('<h1 class="mt-2 text-3xl font-bold">Flight Plan Brief</h1>', escape: false);
        $response->assertSeeText('Your flight release, distilled into the details that matter.');
        $response->assertSeeHtml('wire:id=');
        $response->assertSeeText('Flight release PDF');
        $response->assertSeeText('Extract route');
        $response->assertDontSeeText('Extracted flight plan');
    }

    public function test_non_admin_users_can_not_view_the_flight_plan_brief_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('flight-release.index'))
            ->assertForbidden();
    }

    public function test_verified_non_admin_users_can_view_the_page_during_the_demo(): void
    {
        Config::set('features.flight_release.for_all_users', true);

        $this->actingAs(User::factory()->create())
            ->get(route('flight-release.index'))
            ->assertOk()
            ->assertSeeText('Flight Plan Brief')
            ->assertSeeText('Flight release PDF');
    }

    public function test_flight_plan_brief_returns_not_found_when_the_feature_is_disabled(): void
    {
        Config::set('features.flight_release.enabled', false);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('flight-release.index'))
            ->assertNotFound();
    }

    public function test_the_obsolete_post_route_is_removed(): void
    {
        $this->assertFalse(Route::has('flight-release.store'));
    }
}
