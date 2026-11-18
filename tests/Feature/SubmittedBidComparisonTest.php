<?php

namespace Tests\Feature;

use App\Jobs\RefreshSubmittedBids;
use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\BidSubmission;
use App\Models\BidWorkspace;
use App\Models\CrewBidCredentialVault;
use App\Models\SubmittedBidSnapshot;
use App\Models\User;
use App\Services\CrewBids\BidPortalHttpClient;
use App\Services\CrewBids\CrewBidSecretStore;
use App\Services\CrewBids\QueuedSubmittedBidRefresh;
use App\Services\CrewBids\ReadPersonalSubmittedBids;
use App\Services\CrewBids\ResolveSubmittedBidOrder;
use App\Services\CrewBids\StoreSubmittedBidSnapshot;
use App\View\Models\SubmittedBidComparisonViewModel;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Fixtures\InMemoryCrewBidSecretStore;
use Tests\TestCase;

class SubmittedBidComparisonTest extends TestCase
{
    use RefreshDatabase;

    private const string ORIGIN = 'https://crewbids.kalittaair.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(CrewBidSecretStore::class, new InMemoryCrewBidSecretStore);
    }

    public function test_queued_closed_window_read_establishes_sort_without_workspace_submission_or_bid_update(): void
    {
        Queue::fake([RefreshSubmittedBids::class]);
        config(['crew_bids.submission.enabled' => false]);
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $http = $this->portal(closed: true);

        $response = $this->actingAs($user)->postJson($this->url($package), $this->credentials())->assertStatus(202)
            ->assertHeader('Cache-Control', 'no-store, private')->assertSessionMissing('_old_input')->assertDontSee('synthetic-password');
        $http->assertNothingSent();
        $job = $this->job();
        $this->assertSame('crew-bid-refreshes', $job->connection);
        $this->assertSame('crew-bid-refreshes', $job->queue);
        $this->assertStringNotContainsString('synthetic-password', serialize($job));
        $this->getJson($response->json('url'))->assertOk()->assertJsonPath('state', 'queued');
        $this->runJob($job);

        $this->getJson($response->json('url'))->assertOk()->assertJsonPath('state', 'completed')->assertJsonStructure(['finished_at']);
        $order = app(ResolveSubmittedBidOrder::class)->resolve($user, $package, 'FO');
        $this->assertSame(['F5' => 1, 'R6' => 2, 'Q7' => 3], $order->ranks);
        $this->assertTrue($order->available());
        $snapshot = SubmittedBidSnapshot::query()->sole();
        $this->assertSame([5, 6, 7], $snapshot->evidence['numbers']);
        $this->assertStringNotContainsString('account_hash', $snapshot->getRawOriginal('evidence'));
        $this->assertArrayNotHasKey('evidence', $snapshot->toArray());
        $this->assertNull(app(CrewBidSecretStore::class)->get('submitted:'.$job->refreshId));
        $this->assertDatabaseCount('bid_workspaces', 0);
        $this->assertDatabaseCount('bid_submissions', 0);
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        Livewire::actingAs($user)->test(CrewBids::class)->set('sort', 'submitted')->assertSet('sort', 'submitted')
            ->call('openComparison')->assertSee('K4 submitted bids')->assertSee('Only in K4 submitted bids');
    }

    public function test_compare_and_completion_keep_unsaved_draft_filters_preferences_revision_and_copy_text(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $workspace = BidWorkspace::factory()->create(['user_id' => $user->id, 'bid_package_id' => $package->id, 'selections' => ['F5', 'Q7'], 'revision' => 4]);
        $this->baseline($user, $package);
        $component = Livewire::actingAs($user)->test(CrewBids::class)->set('minimumCredit', 299)
            ->call('openComparison')->assertSet('showComparison', true)->assertSee('same relative order', false);
        $workspace->update(['revision' => 5]);
        $before = $workspace->fresh()->getRawOriginal();
        $component->call('add', 'F1')->assertSet('dirty', true);
        $component->call('move', 'F1', 0)->assertSet('dirty', true);
        $component->call('refreshComparison')->assertSet('selections', ['F1', 'F5', 'Q7'])->assertSet('dirty', true)
            ->assertSet('revision', 4)->assertSet('minimumCredit', 299)->assertSee('Includes unsaved changes')
            ->assertSee('Only in My bid list')->assertSee('Only in K4 submitted bids');
        $this->assertSame($before, $workspace->fresh()->getRawOriginal());
        $this->assertSame("1. F1\n2. F5\n3. Q7", $component->viewData('copyText'));
        $this->assertCount(4, $component->viewData('comparison')->rows);
    }

    public function test_rank_shifts_from_insertions_are_distinct_from_retained_reordering(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $this->baseline($user, $package);

        $inserted = $this->comparison($user, $package, ['F1', 'F5', 'R6', 'Q7']);
        $reordered = $this->comparison($user, $package, ['Q7', 'F5']);

        $this->assertSame(1, $inserted->added);
        $this->assertSame(0, $inserted->removed);
        $this->assertFalse($inserted->reordered);
        $this->assertSame(['Only in My bid list', 'Moved', 'Moved', 'Moved'], array_column($inserted->rows, 'status'));
        $this->assertTrue($reordered->reordered);
        $this->assertSame(1, $reordered->removed);
    }

    #[DataProvider('unmappedLines')]
    public function test_incomplete_mappings_are_shown_but_never_enable_sort_or_match(string $case, string $annotation): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $payload = $package->payload;
        if ($case === 'missing') {
            unset($payload['lines']['F5']);
        } elseif ($case === 'ineligible') {
            $payload['lines']['F5']['first_officers'] = 0;
        } else {
            $payload['lines']['R5'] = array_replace($payload['lines']['F5'], ['id' => 'R5', 'type' => 'reserve']);
        }
        $package->update(['payload' => $payload]);
        $this->baseline($user, $package);

        $order = app(ResolveSubmittedBidOrder::class)->resolve($user, $package, 'FO');
        $comparison = $this->comparison($user, $package, ['F5', 'R6', 'Q7']);

        $this->assertFalse($order->available());
        $this->assertContains($annotation, array_column($comparison->rows, 'annotation'));
        $this->assertTrue($comparison->hasCheck);
        $this->assertSame([5, 6, 7], SubmittedBidSnapshot::query()->sole()->evidence['numbers']);
    }

    public static function unmappedLines(): array
    {
        return ['missing' => ['missing', 'Missing from this package'], 'ineligible' => ['ineligible', 'Not eligible for this seat'], 'ambiguous' => ['ambiguous', 'Ambiguous line mapping']];
    }

    public function test_failed_refresh_preserves_last_check_and_local_workspace_and_cleans_grant(): void
    {
        Queue::fake([RefreshSubmittedBids::class]);
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $snapshot = $this->baseline($user, $package);
        $workspace = BidWorkspace::factory()->create(['user_id' => $user->id, 'bid_package_id' => $package->id, 'selections' => ['F1']]);
        $before = $workspace->fresh()->getRawOriginal();
        $this->portal(duringRead: fn (): string => 'not a PDF');
        $response = $this->actingAs($user)->postJson($this->url($package), $this->credentials())->assertStatus(202);

        $job = $this->job();
        $this->runJob($job);

        $this->getJson($response->json('url'))->assertOk()->assertJsonPath('state', 'failed')->assertDontSee('synthetic-password');
        $this->assertSame($snapshot->id, app(StoreSubmittedBidSnapshot::class)->latest($user, $package, 'FO')->id);
        $this->assertSame($before, $workspace->fresh()->getRawOriginal());
        $this->assertNull(app(CrewBidSecretStore::class)->get('submitted:'.$job->refreshId));
        $this->assertTrue(app(ResolveSubmittedBidOrder::class)->resolve($user, $package, 'FO')->available());
    }

    #[DataProvider('invalidQueuedContexts')]
    public function test_invalid_queued_context_never_publishes_or_reaches_k4(string $change): void
    {
        Queue::fake([RefreshSubmittedBids::class]);
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $http = $this->portal();
        $this->actingAs($user)->postJson($this->url($package), $this->credentials())->assertStatus(202);
        $job = $this->job();
        if ($change === 'expired') {
            $this->travel(11)->minutes();
        } elseif ($change === 'access') {
            $user->update(['crew_bids_access' => false]);
        } elseif ($change === 'epoch') {
            $user->forceFill(['crew_bid_credential_epoch' => (string) Str::uuid()])->save();
        } else {
            $package->update(['source_revision' => 'changed']);
        }

        $this->runJob($job);

        $this->assertDatabaseCount('submitted_bid_snapshots', 0);
        $this->assertNull(app(CrewBidSecretStore::class)->get('submitted:'.$job->refreshId));
        $http->assertNothingSent();
    }

    public static function invalidQueuedContexts(): array
    {
        return ['expired' => ['expired'], 'revoked access' => ['access'], 'epoch changed' => ['epoch'], 'package changed' => ['package']];
    }

    public function test_package_change_during_read_discards_response_and_preserves_history(): void
    {
        Queue::fake([RefreshSubmittedBids::class]);
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $this->baseline($user, $package);
        $this->portal(duringRead: function () use ($package): void {
            $package->update(['source_revision' => 'changed']);
        });
        $response = $this->actingAs($user)->postJson($this->url($package), $this->credentials());

        $this->runJob($this->job());

        $this->getJson($response->json('url'))->assertJsonPath('state', 'failed');
        $this->assertDatabaseCount('submitted_bid_snapshots', 1);
        $this->assertFalse(app(ResolveSubmittedBidOrder::class)->resolve($user, $package->fresh(), 'FO')->available());
        $this->assertTrue($this->comparison($user, $package->fresh(), [])->hasCheck);
    }

    public function test_new_package_can_display_historical_missing_lines_but_requires_a_fresh_check_for_sorting(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $old = $this->package();
        $this->baseline($user, $old);
        $payload = $old->payload;
        unset($payload['lines']['F5']);
        $new = BidPackage::factory()->create(['period' => $old->period, 'fleet' => $old->fleet, 'payload' => $payload]);

        $comparison = $this->comparison($user, $new, ['F5', 'Q7']);

        $this->assertTrue($comparison->hasCheck);
        $this->assertSame('The last K4 check belongs to a different package revision', $comparison->reason);
        $this->assertSame('F5', $comparison->rows[0]['label']);
        $this->assertSame('Missing from this package', $comparison->rows[0]['annotation']);
        $this->assertFalse(app(ResolveSubmittedBidOrder::class)->resolve($user, $new, 'FO')->available());
    }

    public function test_older_response_cannot_replace_newer_completed_evidence(): void
    {
        Queue::fake([RefreshSubmittedBids::class]);
        $this->freezeTime();
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $newer = null;
        $this->portal(duringRead: function () use ($user, $package, &$newer): void {
            $this->travel(1)->seconds();
            $newer = $this->baseline($user, $package, [7, 5], CarbonImmutable::now());
        });
        $response = $this->actingAs($user)->postJson($this->url($package), $this->credentials());

        $this->runJob($this->job());

        $this->getJson($response->json('url'))->assertJsonPath('state', 'failed');
        $this->assertSame($newer->id, app(StoreSubmittedBidSnapshot::class)->latest($user, $package, 'FO')->id);
        $this->assertSame(['Q7' => 1, 'F5' => 2], app(ResolveSubmittedBidOrder::class)->resolve($user, $package, 'FO')->ranks);
    }

    public function test_later_unknown_write_makes_baseline_historical_and_fresh_check_does_not_clear_write_guard(): void
    {
        $this->freezeTime();
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $this->baseline($user, $package, startedAt: CarbonImmutable::now());
        $workspace = BidWorkspace::factory()->create(['user_id' => $user->id, 'bid_package_id' => $package->id]);
        $attempt = BidSubmission::factory()->create(['user_id' => $user->id, 'bid_workspace_id' => $workspace->id, 'status' => 'unknown', 'attempted_at' => now()]);
        $this->assertFalse(app(ResolveSubmittedBidOrder::class)->resolve($user, $package, 'FO')->available());
        $this->assertTrue($this->comparison($user, $package, [])->hasCheck);
        $this->travel(1)->seconds();

        $this->baseline($user, $package, startedAt: CarbonImmutable::now());

        $this->assertTrue(app(ResolveSubmittedBidOrder::class)->resolve($user, $package, 'FO')->available());
        $this->assertSame('unknown', $attempt->fresh()->status);
    }

    #[DataProvider('invalidEvidence')]
    public function test_corrupt_or_cross_context_evidence_never_claims_match(string $field, mixed $value): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $snapshot = $this->baseline($user, $package);
        $evidence = $snapshot->evidence;
        data_set($evidence, $field, $value);
        $snapshot->update(['evidence' => $evidence]);

        $this->assertFalse(app(ResolveSubmittedBidOrder::class)->resolve($user, $package, 'FO')->available());
    }

    public static function invalidEvidence(): array
    {
        return ['owner' => ['owner_id', 987654], 'seat' => ['context.seat', 'CA'], 'fleet' => ['context.fleet', '747'],
            'period' => ['context.period', '2026-11'], 'package' => ['package_id', 987654], 'hash' => ['package_hash', 'changed'],
            'duplicate' => ['numbers', [5, 5]], 'empty unsupported' => ['numbers', []], 'non-number' => ['numbers', ['5']]];
    }

    public function test_identity_change_and_ciphertext_corruption_hide_private_baseline(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $snapshot = $this->baseline($user, $package);
        $user->forceFill(['crew_bid_employee_number' => '900001', 'crew_bid_employee_verified_at' => now()])->save();
        $this->assertFalse(app(ResolveSubmittedBidOrder::class)->resolve($user->fresh(), $package, 'FO')->available());
        $this->assertFalse($this->comparison($user->fresh(), $package, [])->hasCheck);
        DB::table('submitted_bid_snapshots')->where('id', $snapshot->id)->update(['evidence' => 'corrupt']);

        $this->assertFalse(app(ResolveSubmittedBidOrder::class)->resolve($user, $package, 'FO')->available());
    }

    public function test_refresh_status_is_private_to_owner_package_and_seat_and_duplicate_is_rejected(): void
    {
        Queue::fake([RefreshSubmittedBids::class]);
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $response = $this->actingAs($user)->postJson($this->url($package), $this->credentials())->assertStatus(202);
        $this->postJson($this->url($package), $this->credentials())->assertStatus(422)->assertJsonPath('message', 'A personal K4 check is already queued or running.');
        Queue::assertPushed(RefreshSubmittedBids::class, 1);
        $job = $this->job();
        $this->getJson(str_replace('/FO/', '/CA/', $response->json('url')))->assertNotFound();
        $other = BidPackage::factory()->create();
        $this->getJson(route('crew-bids.submissions.check.status', ['package' => $other, 'seat' => 'FO', 'refresh' => $job->refreshId]))->assertNotFound();
        $this->actingAs(User::factory()->crewBidsAccess()->create())->getJson($response->json('url'))->assertNotFound();
    }

    public function test_authentication_access_and_safe_validation_are_enforced(): void
    {
        Queue::fake([RefreshSubmittedBids::class]);
        $package = $this->package();
        $this->postJson($this->url($package), $this->credentials())->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($this->url($package), $this->credentials())->assertForbidden();
        $this->actingAs(User::factory()->crewBidsAccess()->unverified()->create())->postJson($this->url($package), $this->credentials())->assertForbidden();
        $this->actingAs(User::factory()->crewBidsAccess()->create())->postJson($this->url($package), ['employee' => 'bad', 'portal_password' => 'synthetic-password'])
            ->assertStatus(422)->assertDontSee('synthetic-password')->assertSessionMissing('_old_input');
        $package->update(['published_at' => null]);
        $this->postJson($this->url($package), $this->credentials())->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function test_unlocked_vault_can_queue_check_but_locking_before_execution_revokes_it(): void
    {
        Queue::fake([RefreshSubmittedBids::class]);
        config(['crew_bids.vault.enabled' => true]);
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $http = $this->portal();
        $this->actingAs($user)->post(route('crew-bids.connection.unlock'), ['app_password' => 'password'])->assertRedirect()->assertSessionMissing('crew_bid_sync_error');
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $response = $this->postJson($this->url($package), ['use_saved_credentials' => 1])->assertStatus(202);
        $job = $this->job();
        $this->delete(route('crew-bids.connection.lock'))->assertRedirect();

        $this->runJob($job);

        $this->getJson($response->json('url'))->assertJsonPath('state', 'failed');
        $this->postJson($this->url($package), ['use_saved_credentials' => 1])->assertStatus(422)->assertJsonStructure(['message']);
        $this->assertNull(app(CrewBidSecretStore::class)->get('submitted:'.$job->refreshId));
        $this->assertDatabaseCount('submitted_bid_snapshots', 0);
        $http->assertNothingSent();
    }

    public function test_verified_account_mismatch_and_revocation_during_read_preserve_last_check(): void
    {
        Queue::fake([RefreshSubmittedBids::class]);
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $snapshot = $this->baseline($user, $package);
        $this->portal(duringRead: function () use ($user): void {
            $user->forceFill(['crew_bid_employee_number' => '900001', 'crew_bid_employee_verified_at' => now()])->save();
        });
        $response = $this->actingAs($user)->postJson($this->url($package), $this->credentials())->assertStatus(202);

        $this->runJob($this->job());

        $this->getJson($response->json('url'))->assertJsonPath('state', 'failed');
        $this->assertDatabaseCount('submitted_bid_snapshots', 1);
        $this->assertSame($snapshot->id, app(StoreSubmittedBidSnapshot::class)->latest($user, $package, 'FO')->id);
    }

    public function test_worker_terminal_failure_releases_lock_and_removes_credentials(): void
    {
        Queue::fake([RefreshSubmittedBids::class]);
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $response = $this->actingAs($user)->postJson($this->url($package), $this->credentials())->assertStatus(202);
        $job = $this->job();

        $job->failed(new RuntimeException('synthetic-password upstream details'));

        $this->getJson($response->json('url'))->assertJsonPath('state', 'failed')->assertDontSee('synthetic-password');
        $this->assertNull(app(CrewBidSecretStore::class)->get('submitted:'.$job->refreshId));
        $this->assertTrue(Cache::lock($job->lockKey)->get());
        $this->assertDatabaseCount('submitted_bid_snapshots', 0);
    }

    private function package(): BidPackage
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $payload = $package->payload;
        foreach (['F5' => 'flying', 'R6' => 'reserve', 'Q7' => 'reserve'] as $id => $type) {
            $payload['lines'][$id] = array_replace($payload['lines']['F1'], ['id' => $id, 'type' => $type]);
        }
        $package->update(['payload' => $payload]);

        return $package;
    }

    /** @param list<int> $numbers */
    private function baseline(User $user, BidPackage $package, array $numbers = [5, 6, 7], ?CarbonImmutable $startedAt = null): SubmittedBidSnapshot
    {
        $store = app(StoreSubmittedBidSnapshot::class);

        return $store->store($user, $package, 'FO', '900000', ['context' => ['account_matches_input' => true, 'period' => $package->period, 'fleet' => $package->fleet,
            'seat' => 'FO', 'package_id' => 4000, 'package_revision' => 0], 'numbers' => $numbers], (string) Str::ulid(), $startedAt ?? CarbonImmutable::now()->subSecond(), CarbonImmutable::now(), $store->packageHash($package), attemptId: $store->attemptId($user, $package, 'FO'));
    }

    /** @param list<string> $selections */
    private function comparison(User $user, BidPackage $package, array $selections): SubmittedBidComparisonViewModel
    {
        return SubmittedBidComparisonViewModel::make($user, $package, 'FO', $selections, app(ResolveSubmittedBidOrder::class)->resolve($user, $package, 'FO'), app(StoreSubmittedBidSnapshot::class));
    }

    private function credentials(): array
    {
        return ['employee' => '900000', 'portal_password' => ' synthetic-password '];
    }

    private function url(BidPackage $package): string
    {
        return route('crew-bids.submissions.check.store', ['package' => $package, 'seat' => 'FO']);
    }

    private function job(): RefreshSubmittedBids
    {
        Queue::assertPushed(RefreshSubmittedBids::class);

        return Queue::pushed(RefreshSubmittedBids::class)->last();
    }

    private function runJob(RefreshSubmittedBids $job): void
    {
        $job->handle(app(QueuedSubmittedBidRefresh::class), app(ReadPersonalSubmittedBids::class));
    }

    private function portal(bool $closed = false, ?Closure $duringRead = null): BidPortalHttpClient
    {
        $http = new BidPortalHttpClient;
        $http->preventStrayRequests();
        $this->app->instance(BidPortalHttpClient::class, $http);
        $sheet = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-open-bid-sheet.html'));
        $http->fake([
            self::ORIGIN.'/' => fn (Request $request) => $request->method() === 'GET'
                ? $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html')))
                : $http->response('', 302, ['Location' => '/Pages/BidSheet.aspx']),
            self::ORIGIN.'/Pages/BidSheet.aspx' => $http->response($closed ? str_replace('>Open<', '>Closed<', $sheet) : $sheet),
            self::ORIGIN.'/Pages/BidLineDataOutput.aspx' => $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-submitted-report.html'))),
            self::ORIGIN.'/Reserved.ReportViewerWebControl.axd?*' => function () use ($http, $duringRead) {
                return $http->response(($duringRead === null ? null : $duringRead()) ?? file_get_contents(base_path('tests/Fixtures/crew-bids/submitted-before.pdf')));
            },
        ]);

        return $http;
    }
}
