<?php

namespace Tests\Feature;

use App\Jobs\RefreshCrewBidSnapshot;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\CrewBidSnapshot;
use App\Models\PilotSeniorityList;
use App\Models\User;
use App\Services\CrewBids\QueuedCrewBidRefresh;
use App\Services\CrewBids\SyncCrewBidSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\SharedBidPortal;
use Tests\TestCase;

class CrewBidEmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_viewer_requires_login_access_and_an_available_package_before_contacting_the_portal(): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $http = SharedBidPortal::fake();
        $url = route('crew-bids.employees.index', $package);
        $this->get($url)->assertRedirect(route('login'));
        $this->post($url, $this->credentials())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $user = User::factory()->crewBidsAccess()->create();
        $this->actingAs($user);
        $package->update(['published_at' => null]);
        $this->get($url)->assertNotFound();
        $this->post($url, $this->credentials())->assertNotFound();
        $package->update(['published_at' => now(), 'archived_at' => now()]);
        $this->get($url)->assertNotFound();
        BidWorkspace::factory()->create(['bid_package_id' => $package->id, 'user_id' => $user->id]);
        $this->get($url)->assertOk();
        $http->assertNothingSent();
    }

    public function test_refresh_is_shared_with_another_user_and_does_not_change_private_bids_or_retain_credentials(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $other = User::factory()->crewBidsAccess()->create();
        $other->forceFill(['crew_bid_employee_number' => '910001', 'crew_bid_employee_verified_at' => now()])->save();
        PilotSeniorityList::factory()->create();
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $workspace = BidWorkspace::factory()->create(['bid_package_id' => $package->id, 'user_id' => $user->id, 'selections' => ['F1', 'R2']]);
        $before = $workspace->fresh()->getRawOriginal();
        $http = SharedBidPortal::fake();
        $url = route('crew-bids.employees.read', $package);
        $this->actingAs($user)->get($url)->assertOk()->assertSee('No shared bid snapshot');

        $this->post($url, $this->credentials())->assertRedirect(route('crew-bids.employees.index', ['package' => $package, 'seat' => 'FO']))
            ->assertSessionHas('crew_bid_sync_notice', 'Shared-bid refresh queued. You can keep using the app.');
        $http->assertNothingSent();
        $this->runRefresh();

        $this->actingAs($other)->get($url)->assertOk()->assertSee(['Example, Avery', 'Your submitted choices'])
            ->assertDontSee('Sample, Morgan')->assertDontSee('name="bidder"', false)
            ->assertDontSee('synthetic-password')->assertDontSee('900000')->assertDontSee('directory-state')
            ->assertDontSee('ReportID=')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get($url.'?bidder=910002')->assertOk()->assertSee(['Example, Avery', 'Original submitted choice order'])->assertDontSee('Sample, Morgan');
        $this->assertSame($before, $workspace->fresh()->getRawOriginal());
        $this->assertDatabaseCount('bid_submissions', 0);
        $this->assertDatabaseCount('bid_workspaces', 1);
        $this->assertDatabaseCount('crew_bid_snapshots', 1);
        $session = json_encode(session()->all(), JSON_THROW_ON_ERROR);
        foreach (['900000', 'synthetic-password', 'Example, Avery'] as $private) {
            $this->assertStringNotContainsString($private, $session);
        }
        $this->assertFalse(config('debugbar.enabled'));
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        $http->assertSentCount(8);
    }

    public function test_shared_reads_need_no_credentials_and_remain_isolated_by_month_fleet_and_position(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910001', 'crew_bid_employee_verified_at' => now()])->save();
        PilotSeniorityList::factory()->create();
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        CrewBidSnapshot::factory()->create(['period' => '2026-12']);
        $http = SharedBidPortal::fake();
        $url = route('crew-bids.employees.index', $package);

        $this->actingAs($user)->get($url)->assertOk()->assertSee('Example, Avery');
        $this->get($url.'?seat=CA')->assertOk()->assertSee('No shared bid snapshot')->assertDontSee('Your submitted choices');
        $anotherFleet = BidPackage::factory()->create(['period' => '2026-12', 'fleet' => '747']);
        $this->get(route('crew-bids.employees.index', $anotherFleet))->assertOk()->assertSee('No shared bid snapshot');
        $anotherMonth = BidPackage::factory()->create(['period' => '2026-11']);
        $this->get(route('crew-bids.employees.index', $anotherMonth))->assertOk()->assertSee('No shared bid snapshot');
        $this->get($url.'?seat=FE')->assertNotFound();
        $http->assertNothingSent();
    }

    public function test_invalid_credentials_and_position_show_safe_feedback_without_flashing_input(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create();
        $http = SharedBidPortal::fake();

        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), ['employee' => 'bad-number', 'portal_password' => 'synthetic-password', 'seat' => 'FE'])
            ->assertRedirect()->assertSessionHas('crew_bid_sync_error')->assertSessionMissing('_old_input');
        $this->get(route('crew-bids.employees.index', $package))->assertOk()->assertSee('Enter your employee number and portal password')->assertDontSee('bad-number')->assertDontSee('synthetic-password');

        $http->assertNothingSent();
        $this->assertDatabaseCount('crew_bid_snapshots', 0);
    }

    public function test_pdf_download_has_private_attachment_headers_and_updates_the_shared_snapshot(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $http = SharedBidPortal::fake();

        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials() + ['format' => 'PDF'])->assertRedirect();
        $http->assertNothingSent();
        $refresh = $this->runRefresh();
        $this->get(route('crew-bids.employees.refresh.download', ['package' => $package, 'refresh' => $refresh]))
            ->assertOk()->assertContent('%PDF-synthetic-global-export')->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="shared-bids-2026-12-FO.pdf"')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertDatabaseCount('bid_submissions', 0);
        $this->assertDatabaseCount('bid_workspaces', 0);
        $this->assertDatabaseCount('crew_bid_snapshots', 1);
        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'OpType=Export') && str_ends_with($request->url(), 'Format=PDF'));
    }

    public function test_upstream_failure_keeps_shared_data_and_never_exposes_remote_error_details(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910001', 'crew_bid_employee_verified_at' => now()])->save();
        PilotSeniorityList::factory()->create();
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        CrewBidSnapshot::factory()->create(['period' => '2026-12']);
        $http = SharedBidPortal::fake('connection');

        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect()->assertSessionMissing('crew_bid_sync_error');
        $this->runRefresh();
        $this->get(route('crew-bids.employees.index', $package))->assertOk()->assertSee('The portal operation could not be completed.')
            ->assertSee('Example, Avery')->assertDontSee('private sensitive upstream detail')->assertDontSee('synthetic-password');

        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/Pages/'));
    }

    public function test_login_page_during_refresh_explains_the_failure_and_preserves_the_last_snapshot(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $snapshot = CrewBidSnapshot::factory()->create(['period' => '2026-12']);
        $before = $snapshot->fresh()->getRawOriginal();
        $http = SharedBidPortal::fake('expired-login');

        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials())
            ->assertRedirect()->assertSessionMissing('crew_bid_sync_error')
            ->assertSessionMissing('_old_input');
        $refresh = $this->runRefresh();
        $this->get(route('crew-bids.employees.refresh.status', ['package' => $package, 'refresh' => $refresh]))->assertOk()
            ->assertJsonPath('state', 'failed')->assertJsonPath('message', 'The portal returned a login page instead of an authenticated Bid Sheet. Check your portal login and try again.');

        $this->assertSame($before, $snapshot->fresh()->getRawOriginal());
        $this->assertDatabaseCount('crew_bid_snapshots', 1);
        $this->assertDatabaseCount('bid_submissions', 0);
        $http->assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/Pages/ViewAllBids.aspx'));
    }

    public function test_portal_employee_names_are_escaped_in_the_current_users_report(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910001', 'crew_bid_employee_verified_at' => now()])->save();
        PilotSeniorityList::factory()->create();
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $name = '<img src=x onerror=alert(1)>';
        $http = SharedBidPortal::fake(name: $name);
        $url = route('crew-bids.employees.index', $package);
        $this->actingAs($user)->post($url, $this->credentials())->assertRedirect();
        $this->runRefresh();

        $this->get($url.'?bidder=910001')->assertOk()->assertSee($name)->assertDontSee($name, false);

        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'OpType=ReportArea'));
    }

    private function credentials(): array
    {
        return ['employee' => '900000', 'portal_password' => 'synthetic-password', 'seat' => 'FO'];
    }

    private function runRefresh(): string
    {
        Queue::assertPushed(RefreshCrewBidSnapshot::class);
        $job = Queue::pushed(RefreshCrewBidSnapshot::class)->first();
        $job->handle(app(QueuedCrewBidRefresh::class), app(SyncCrewBidSnapshot::class));

        return $job->refreshId;
    }
}
