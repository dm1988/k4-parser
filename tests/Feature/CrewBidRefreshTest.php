<?php

namespace Tests\Feature;

use App\Jobs\RefreshCrewBidSnapshot;
use App\Models\BidPackage;
use App\Models\CrewBidSnapshot;
use App\Models\User;
use App\Services\CrewBids\QueuedCrewBidRefresh;
use App\Services\CrewBids\SyncCrewBidSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Fixtures\SharedBidPortal;
use Tests\TestCase;

class CrewBidRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_ranks_fail_in_background_without_replacing_the_last_good_snapshot(): void
    {
        Queue::fake();
        $http = SharedBidPortal::fake('missing-rank');
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $snapshot = CrewBidSnapshot::factory()->create(['period' => '2026-12']);
        $before = $snapshot->fresh()->getRawOriginal();

        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect()
            ->assertSessionMissing('_old_input')->assertSessionMissing('crew_bid_sync_error');
        $http->assertNothingSent();
        $job = $this->job();
        $this->get($this->statusUrl($package, $job))->assertOk()->assertJsonPath('state', 'queued');
        $job->handle(app(QueuedCrewBidRefresh::class), app(SyncCrewBidSnapshot::class));

        $this->get($this->statusUrl($package, $job))->assertOk()->assertJsonPath('state', 'failed')
            ->assertJsonPath('message', 'The global and individual reports disagree on choice ranks. Refresh again.')
            ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('synthetic-password');
        $this->assertSame($before, $snapshot->fresh()->getRawOriginal());
        $this->assertNull(Cache::get('crew-bid-refresh:'.$job->refreshId.':credentials'));
        $this->assertDatabaseCount('crew_bid_snapshots', 1);
        $this->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect()->assertSessionMissing('crew_bid_sync_error');
        Queue::assertPushed(RefreshCrewBidSnapshot::class, 2);
    }

    public function test_another_user_cannot_read_status_or_download_an_export_even_with_the_refresh_id(): void
    {
        Queue::fake();
        $http = SharedBidPortal::fake();
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials() + ['format' => 'Excel'])->assertRedirect();
        $job = $this->job();
        $job->handle(app(QueuedCrewBidRefresh::class), app(SyncCrewBidSnapshot::class));
        $download = route('crew-bids.employees.refresh.download', ['package' => $package, 'refresh' => $job->refreshId]);

        $this->get($download)->assertOk()->assertHeader('Content-Type', 'application/vnd.ms-excel');
        $this->actingAs(User::factory()->crewBidsAccess()->create())->get($this->statusUrl($package, $job))->assertNotFound();
        $this->get($download)->assertNotFound();
        $otherPackage = BidPackage::factory()->create();
        $this->actingAs($user)->get($this->statusUrl($otherPackage, $job))->assertNotFound();
        $this->get(route('crew-bids.employees.index', $package))->assertOk()->assertSee('Download Excel')->assertDontSee('synthetic-password');
        $this->assertNull(Cache::get('crew-bid-refresh:'.$job->refreshId.':credentials'));
        $http->assertSentCount(9);
    }

    public function test_duplicate_refreshes_for_the_same_context_do_not_queue_another_portal_login(): void
    {
        Queue::fake();
        $http = SharedBidPortal::fake();
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create();
        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect();

        $this->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect()
            ->assertSessionHas('crew_bid_sync_error', 'A shared refresh is already queued or running for this position. Try again shortly.');

        Queue::assertPushed(RefreshCrewBidSnapshot::class, 1);
        $http->assertNothingSent();
        $this->assertDatabaseCount('crew_bid_snapshots', 0);
    }

    public function test_expired_queued_credentials_are_discarded_and_the_user_can_retry(): void
    {
        Queue::fake();
        $http = SharedBidPortal::fake();
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create();
        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect();
        $job = $this->job();
        $this->travel(601)->seconds();

        $this->get($this->statusUrl($package, $job))->assertOk()->assertJsonPath('state', 'failed');
        $job->handle(app(QueuedCrewBidRefresh::class), app(SyncCrewBidSnapshot::class));
        $this->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect()->assertSessionMissing('crew_bid_sync_error');

        Queue::assertPushed(RefreshCrewBidSnapshot::class, 2);
        $this->assertNull(Cache::get('crew-bid-refresh:'.$job->refreshId.':credentials'));
        $http->assertNothingSent();
    }

    public function test_worker_rechecks_access_and_package_context_before_using_a_queued_login(): void
    {
        Queue::fake();
        $http = SharedBidPortal::fake();
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create();
        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect();
        $job = $this->job();
        $user->update(['crew_bids_access' => false]);

        $job->handle(app(QueuedCrewBidRefresh::class), app(SyncCrewBidSnapshot::class));

        $user->update(['crew_bids_access' => true]);
        $this->get($this->statusUrl($package, $job))->assertOk()->assertJsonPath('state', 'failed');
        $this->assertNull(Cache::get('crew-bid-refresh:'.$job->refreshId.':credentials'));
        $http->assertNothingSent();
        $this->assertDatabaseCount('crew_bid_snapshots', 0);
    }

    public function test_changed_package_rejects_the_queued_login_without_contacting_the_portal(): void
    {
        Queue::fake();
        $http = SharedBidPortal::fake();
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create();
        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect();
        $job = $this->job();
        $package->update(['source_revision' => 'changed']);

        $job->handle(app(QueuedCrewBidRefresh::class), app(SyncCrewBidSnapshot::class));

        $this->get($this->statusUrl($package, $job))->assertOk()->assertJsonPath('state', 'failed')
            ->assertJsonPath('message', 'This bid package changed while the refresh was queued. Open it again and retry.');
        $http->assertNothingSent();
    }

    public function test_timeout_failure_cleans_up_credentials_and_allows_a_new_attempt(): void
    {
        Queue::fake();
        $http = SharedBidPortal::fake();
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create();
        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect();
        $job = $this->job();

        $job->failed(new RuntimeException('private worker detail'));

        $this->get($this->statusUrl($package, $job))->assertOk()->assertJsonPath('state', 'failed')->assertDontSee('private worker detail');
        $this->assertNull(Cache::get('crew-bid-refresh:'.$job->refreshId.':credentials'));
        $this->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect()->assertSessionMissing('crew_bid_sync_error');
        Queue::assertPushed(RefreshCrewBidSnapshot::class, 2);
        $http->assertNothingSent();
    }

    public function test_refreshes_queued_before_the_status_metadata_update_fail_safely_and_allow_retry(): void
    {
        Queue::fake();
        $http = SharedBidPortal::fake();
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create();
        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect();
        $job = $this->job();
        $key = 'crew-bid-refresh:'.$job->refreshId;
        $legacy = Cache::get($key);
        unset($legacy['lock_key'], $legacy['lock_owner'], $legacy['package_hash']);
        Cache::put($key, $legacy, 900);

        $job->handle(app(QueuedCrewBidRefresh::class), app(SyncCrewBidSnapshot::class));

        $this->get($this->statusUrl($package, $job))->assertOk()->assertJsonPath('state', 'failed')
            ->assertJsonPath('message', 'This refresh was queued before an app update. Enter your portal login again to retry.');
        $this->assertNull(Cache::get($key.':credentials'));
        $this->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect()->assertSessionMissing('crew_bid_sync_error');
        Queue::assertPushed(RefreshCrewBidSnapshot::class, 2);
        $http->assertNothingSent();
        $this->assertDatabaseCount('crew_bid_snapshots', 0);
    }

    public function test_dispatch_failure_cleans_up_temporary_login_and_releases_the_context_lock(): void
    {
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('private dispatch detail'));
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create();

        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect()
            ->assertSessionHas('crew_bid_sync_error')->assertSessionMissing('_old_input');

        $this->assertNull(app(QueuedCrewBidRefresh::class)->current($user, $package, 'FO'));
        $lock = Cache::lock('crew-bid-refresh-active:'.$package->period.':'.$package->fleet.':FO');
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_real_queue_payload_has_only_an_encrypted_reference_and_the_request_never_calls_the_portal(): void
    {
        $http = SharedBidPortal::fake();
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create(['period' => '2026-12']);

        app(QueuedCrewBidRefresh::class)->start($user, $package, 'FO', '900000', 'synthetic-password', null);

        $this->assertDatabaseCount('jobs', 1);
        $payload = DB::table('jobs')->sole()->payload;
        $this->assertStringNotContainsString('synthetic-password', $payload);
        $this->assertStringNotContainsString('900000', $payload);
        $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $job = unserialize(Crypt::decrypt($data['data']['command']), ['allowed_classes' => [RefreshCrewBidSnapshot::class]]);
        $this->assertInstanceOf(RefreshCrewBidSnapshot::class, $job);
        $this->assertStringNotContainsString('synthetic-password', serialize($job));
        $secret = Cache::get('crew-bid-refresh:'.$job->refreshId.':credentials');
        $this->assertStringNotContainsString('synthetic-password', $secret);
        $this->assertStringContainsString('synthetic-password', Crypt::decryptString($secret));
        $http->assertNothingSent();

        $queued = Queue::connection('crew-bid-refreshes')->pop('crew-bid-refreshes');
        $this->assertNotNull($queued);
        $queued->fire();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('crew_bid_snapshots', 1);
        $this->assertSame('completed', app(QueuedCrewBidRefresh::class)->current($user, $package, 'FO')['state']);
        $this->assertNull(Cache::get('crew-bid-refresh:'.$job->refreshId.':credentials'));
        $http->assertSentCount(8);
    }

    public function test_running_state_is_visible_and_unexpected_errors_are_sanitized_after_login_is_consumed(): void
    {
        Queue::fake();
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create();
        $snapshot = CrewBidSnapshot::factory()->create(['period' => $package->period, 'fleet' => $package->fleet]);
        $before = $snapshot->fresh()->getRawOriginal();
        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), $this->credentials())->assertRedirect();
        $job = $this->job();
        $observed = [];
        $this->mock(SyncCrewBidSnapshot::class)->shouldReceive('handle')->once()->andReturnUsing(function () use ($job, $user, $package, &$observed): never {
            $observed = [app(QueuedCrewBidRefresh::class)->current($user, $package, 'FO')['state'], Cache::get('crew-bid-refresh:'.$job->refreshId.':credentials')];
            throw new RuntimeException('private upstream detail synthetic-password');
        });

        $job->handle(app(QueuedCrewBidRefresh::class), app(SyncCrewBidSnapshot::class));

        $this->assertSame(['running', null], $observed);
        $this->get($this->statusUrl($package, $job))->assertOk()->assertJsonPath('state', 'failed')
            ->assertDontSee('private upstream detail')->assertDontSee('synthetic-password');
        $this->assertSame($before, $snapshot->fresh()->getRawOriginal());
    }

    private function credentials(): array
    {
        return ['employee' => '900000', 'portal_password' => 'synthetic-password', 'seat' => 'FO'];
    }

    private function job(): RefreshCrewBidSnapshot
    {
        Queue::assertPushed(RefreshCrewBidSnapshot::class);

        return Queue::pushed(RefreshCrewBidSnapshot::class)->first();
    }

    private function statusUrl(BidPackage $package, RefreshCrewBidSnapshot $job): string
    {
        return route('crew-bids.employees.refresh.status', ['package' => $package, 'refresh' => $job->refreshId]);
    }
}
