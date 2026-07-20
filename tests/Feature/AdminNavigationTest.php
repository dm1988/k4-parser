<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_hydrates_the_latest_parser_result_from_cache(): void
    {
        $parseKey = '01JTESTPARSEKEYABC123';
        $namespace = '01JTESTSESSIONKEYABC123';
        session([
            'latest_parse_key' => $parseKey,
            'parsed_results_namespace' => $namespace,
        ]);
        Cache::put("sessions:{$namespace}:parsed_results:{$parseKey}", [
            'type' => 'roster',
            'source' => 'text',
            'filters' => [],
            'parse_key' => $parseKey,
            'parsed' => [
                'trip' => ['trip_number' => '13131'],
                'calendar_events' => [[
                    'title' => 'Hotel Check-In',
                    'type' => 'duty',
                    'start' => '2026-06-13T14:00:00+00:00',
                    'end' => '2026-06-13T16:00:00+00:00',
                    'download_id' => '01JTESTEVENTKEYABC123',
                    'metadata' => [],
                ]],
            ],
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Extracted Schedule')
            ->assertSeeText('Hotel Check-In');
    }

    public function test_eligible_admins_can_see_the_admin_navigation_link(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill([
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('Admin Panel')
            ->assertSee(route('filament.admin.pages.dashboard'), escape: false);
    }

    public function test_non_admin_users_can_not_see_the_admin_navigation_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSeeText('Admin Panel')
            ->assertDontSee(route('filament.admin.pages.dashboard'), escape: false);
    }

    public function test_navigation_uses_safe_links_and_forms_without_inline_javascript(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('https://buymeacoffee.com/crewcompass', escape: false)
            ->assertSee('rel="noopener noreferrer"', escape: false)
            ->assertSee('action="'.route('logout').'"', escape: false)
            ->assertDontSee('cdnjs.buymeacoffee.com', escape: false)
            ->assertDontSee('onclick=', escape: false);
    }

    public function test_inactive_verified_admins_can_not_see_the_admin_navigation_link(): void
    {
        $admin = User::factory()->admin()->inactive()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Admin Panel')
            ->assertDontSee(route('filament.admin.pages.dashboard'), escape: false);
    }

    public function test_unverified_admins_are_redirected_to_the_verification_notice(): void
    {
        $admin = User::factory()->admin()->unverified()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }
}
