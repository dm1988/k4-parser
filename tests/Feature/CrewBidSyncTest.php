<?php

namespace Tests\Feature;

use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\User;
use App\Services\CrewBids\BidPortalHttpClient;
use App\Services\CrewBids\CrewBidSecretStore;
use App\Services\CrewBids\ResolveSubmittedBidOrder;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\InMemoryCrewBidSecretStore;
use Tests\TestCase;

class CrewBidSyncTest extends TestCase
{
    use RefreshDatabase;

    private const string ORIGIN = 'https://crewbids.kalittaair.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(CrewBidSecretStore::class, new InMemoryCrewBidSecretStore);
    }

    public function test_imports_report_order_into_a_new_workspace_without_changing_k4_or_retaining_credentials(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $other = BidWorkspace::factory()->create(['bid_package_id' => $package->id, 'selections' => ['F1']]);
        $sheet = str_replace(['value="5"', 'value="6"', 'value="7"'], ['value="1"', 'value="2"', 'value=""'], file_get_contents(base_path('tests/Fixtures/crew-bids/portal-open-bid-sheet.html')));
        $http = $this->portal($sheet);
        config(['crew_bids.submission.enabled' => false]);
        $this->actingAs($user)->get($this->url($package))->assertOk()->assertSee('My bid list is empty')->assertDontSee('name="overwrite"', false);

        $this->post($this->url($package), $this->credentials() + ['revision' => 0])->assertRedirect(route('crew-bids.index'));

        $workspace = BidWorkspace::query()->where('user_id', $user->id)->sole();
        $this->assertSame(['F5', 'R6', 'Q7'], $workspace->selections);
        $this->assertSame(1, $workspace->revision);
        $this->assertSame(['F5' => 1, 'R6' => 2, 'Q7' => 3], app(ResolveSubmittedBidOrder::class)->resolve($user, $package, 'FO')->ranks);
        $this->assertSame(['F1'], $other->fresh()->selections);
        $this->assertDatabaseCount('bid_submissions', 0);
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        $this->assertFalse(config('debugbar.enabled'));
        $session = json_encode(session()->all(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('900000', $session);
        $this->assertStringNotContainsString('synthetic-password', $session);
        Livewire::actingAs($user)->test(CrewBids::class)->assertSet('showList', true)->assertSet('showPreferences', false)
            ->assertSet('selections', ['F5', 'R6', 'Q7'])->assertSee('Submitted bids synced from K4 in their original order.');
    }

    public function test_imports_into_an_existing_empty_list_and_preserves_preferences_even_when_imported_lines_do_not_match(): void
    {
        [$user, $package, $workspace] = $this->workspace([]);
        $workspace->update(['preferences' => ['goals' => ['credit'], 'days_off' => [], 'start_city' => 'JFK', 'initialized' => true]]);
        $preferences = $workspace->preferences;
        $http = $this->portal();

        $this->actingAs($user)->post($this->url($package), $this->credentials() + ['revision' => 1])->assertRedirect(route('crew-bids.index'));

        $this->assertSame(['F5', 'R6', 'Q7'], $workspace->fresh()->selections);
        $this->assertSame($preferences, $workspace->fresh()->preferences);
        $this->assertSame(2, $workspace->fresh()->revision);
        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'Reserved.ReportViewerWebControl.axd'));
    }

    public function test_returns_to_the_imported_seat_without_changing_the_users_seat_preference(): void
    {
        $user = User::factory()->crewBidsAccess()->create(['crew_bid_seat_preference' => 'FO']);
        $package = $this->package();
        $sheet = str_replace('>FO<', '>CA<', file_get_contents(base_path('tests/Fixtures/crew-bids/portal-open-bid-sheet.html')));
        $http = $this->portal($sheet);
        $url = route('crew-bids.submissions.sync.store', ['package' => $package, 'seat' => 'CA']);

        $this->actingAs($user)->post($url, $this->credentials() + ['revision' => 0])->assertRedirect(route('crew-bids.index'));

        $workspace = BidWorkspace::query()->where('user_id', $user->id)->sole();
        $this->assertSame('CA', $workspace->seat);
        $this->assertSame(['F5', 'R6', 'Q7'], $workspace->selections);
        Livewire::actingAs($user)->test(CrewBids::class)->assertSet('seat', 'CA')->assertSet('showList', true)->assertSet('packageId', $package->id);
        $this->assertSame('FO', $user->fresh()->crew_bid_seat_preference);
        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'Reserved.ReportViewerWebControl.axd'));
    }

    public function test_populated_list_warns_and_requires_overwrite_confirmation_before_any_portal_request(): void
    {
        [$user, $package, $workspace] = $this->workspace(['F1']);
        $http = $this->portal();
        $this->actingAs($user)->get($this->url($package))->assertOk()->assertSee('Syncing will overwrite all 1 choices')
            ->assertSee('name="overwrite"', false)->assertSee('Replace my existing bid list');

        $this->post($this->url($package), $this->credentials() + ['revision' => 1])->assertRedirect($this->url($package))
            ->assertSessionHas('crew_bid_import_error', 'Confirm replacement of your existing bid list before syncing from K4.');

        $this->assertSame(['F1'], $workspace->fresh()->selections);
        $this->assertSame(1, $workspace->fresh()->revision);
        $http->assertNothingSent();
    }

    public function test_confirmed_overwrite_replaces_the_entire_list_in_report_order(): void
    {
        [$user, $package, $workspace] = $this->workspace(['F1', 'Q7']);
        $http = $this->portal();

        $this->actingAs($user)->post($this->url($package), $this->credentials() + ['revision' => 1, 'overwrite' => 1])->assertRedirect(route('crew-bids.index'));

        $this->assertSame(['F5', 'R6', 'Q7'], $workspace->fresh()->selections);
        $this->assertSame(2, $workspace->fresh()->revision);
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_never_contacts_the_portal_or_flashes_credentials(array $input): void
    {
        [$user, $package, $workspace] = $this->workspace(['F1']);
        $http = $this->portal();

        $this->actingAs($user)->post($this->url($package), $input)->assertRedirect($this->url($package))
            ->assertSessionHas('crew_bid_import_error', 'Enter your employee number and portal password, and reopen Sync from K4 before retrying.')
            ->assertSessionMissing('_old_input');

        $this->assertSame(['F1'], $workspace->fresh()->selections);
        $http->assertNothingSent();
    }

    public static function invalidInputs(): array
    {
        $valid = ['employee' => '900000', 'portal_password' => 'synthetic-password', 'revision' => 1, 'overwrite' => 1];

        return [
            'missing credentials' => [[]],
            'invalid employee' => [array_replace($valid, ['employee' => 'private-invalid'])],
            'invalid revision' => [array_replace($valid, ['revision' => -1])],
            'invalid confirmation' => [array_replace($valid, ['overwrite' => 'yes'])],
        ];
    }

    #[DataProvider('mismatchedContexts')]
    public function test_mismatched_portal_context_preserves_the_saved_list(string $search, string $replacement): void
    {
        [$user, $package, $workspace] = $this->workspace(['F1']);
        $sheet = str_replace($search, $replacement, file_get_contents(base_path('tests/Fixtures/crew-bids/portal-open-bid-sheet.html')));
        $http = $this->portal($sheet);

        $this->actingAs($user)->post($this->url($package), $this->credentials() + ['revision' => 1, 'overwrite' => 1])
            ->assertRedirect($this->url($package))->assertSessionHas('crew_bid_import_error');

        $this->assertSame(['F1'], $workspace->fresh()->selections);
        $this->assertSame(1, $workspace->fresh()->revision);
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
    }

    public static function mismatchedContexts(): array
    {
        return [
            'month' => ['12/2026', '11/2026'],
            'fleet' => ['777', '747'],
            'seat' => ['>FO<', '>CA<'],
            'account' => ['>900000<', '>900001<'],
        ];
    }

    #[DataProvider('unmappableLines')]
    public function test_unmappable_submitted_line_preserves_the_whole_list(string $case): void
    {
        [$user, $package, $workspace] = $this->workspace(['F1']);
        $payload = $package->payload;
        if ($case === 'missing') {
            unset($payload['lines']['R6']);
        } elseif ($case === 'ineligible') {
            $payload['lines']['R6']['first_officers'] = 0;
        } else {
            $payload['lines']['F6'] = array_replace($payload['lines']['R6'], ['id' => 'F6', 'type' => 'flying']);
        }
        $package->update(['payload' => $payload]);
        $http = $this->portal();

        $this->actingAs($user)->post($this->url($package), $this->credentials() + ['revision' => 1, 'overwrite' => 1])
            ->assertSessionHas('crew_bid_import_error', 'A submitted line is unavailable or ambiguous in this package and seat. Your bid list is unchanged.');

        $this->assertSame(['F1'], $workspace->fresh()->selections);
        $this->assertSame(1, $workspace->fresh()->revision);
        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'Reserved.ReportViewerWebControl.axd'));
    }

    public static function unmappableLines(): array
    {
        return ['missing' => ['missing'], 'ineligible' => ['ineligible'], 'ambiguous' => ['ambiguous']];
    }

    public function test_stale_revision_is_rejected_before_contacting_k4(): void
    {
        [$user, $package, $workspace] = $this->workspace(['F1']);
        $http = $this->portal();

        $this->actingAs($user)->post($this->url($package), $this->credentials() + ['revision' => 0, 'overwrite' => 1])
            ->assertSessionHas('crew_bid_import_error', 'Your bid list changed. Reopen Sync from K4 and review the saved list before retrying.');

        $this->assertSame(['F1'], $workspace->fresh()->selections);
        $http->assertNothingSent();
    }

    public function test_edits_from_another_tab_during_the_read_are_not_overwritten(): void
    {
        [$user, $package, $workspace] = $this->workspace([]);
        $http = $this->portal(duringRead: function () use ($workspace): void {
            $workspace->update(['selections' => ['F1'], 'revision' => 2]);
        });

        $this->actingAs($user)->post($this->url($package), $this->credentials() + ['revision' => 1])
            ->assertSessionHas('crew_bid_import_error', 'Your bid list changed or could not be saved. Reopen Sync from K4 before retrying.');

        $this->assertSame(['F1'], $workspace->fresh()->selections);
        $this->assertSame(2, $workspace->fresh()->revision);
        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'Reserved.ReportViewerWebControl.axd'));
    }

    public function test_changed_package_during_the_read_is_not_used_for_import(): void
    {
        [$user, $package, $workspace] = $this->workspace(['F1']);
        $http = $this->portal(duringRead: function () use ($package): void {
            $package->update(['source_revision' => 'new revision']);
        });

        $this->actingAs($user)->post($this->url($package), $this->credentials() + ['revision' => 1, 'overwrite' => 1])
            ->assertSessionHas('crew_bid_import_error', 'The bid package changed during sync. Reopen Sync from K4 before retrying.');

        $this->assertSame(['F1'], $workspace->fresh()->selections);
        $this->assertSame(1, $workspace->fresh()->revision);
        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'Reserved.ReportViewerWebControl.axd'));
    }

    #[DataProvider('failedReports')]
    public function test_failed_report_preserves_choices_and_discards_sensitive_exception_details(bool $timeout): void
    {
        [$user, $package, $workspace] = $this->workspace(['F1']);
        $http = $this->portal(duringRead: static function () use ($timeout): string {
            if ($timeout) {
                throw new ConnectionException('synthetic-password private upstream failure');
            }

            return 'not a PDF';
        });

        $this->actingAs($user)->post($this->url($package), $this->credentials() + ['revision' => 1, 'overwrite' => 1])
            ->assertSessionHas('crew_bid_import_error')->assertSessionMissing('_old_input');

        $this->assertSame(['F1'], $workspace->fresh()->selections);
        $this->assertSame(1, $workspace->fresh()->revision);
        $this->assertStringNotContainsString('synthetic-password', json_encode(session()->all(), JSON_THROW_ON_ERROR));
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
    }

    public static function failedReports(): array
    {
        return ['timeout' => [true], 'unreadable' => [false]];
    }

    public function test_closed_window_still_allows_read_only_sync(): void
    {
        [$user, $package, $workspace] = $this->workspace([]);
        $sheet = str_replace('>Open<', '>Closed<', file_get_contents(base_path('tests/Fixtures/crew-bids/portal-open-bid-sheet.html')));
        $http = $this->portal($sheet);

        $this->actingAs($user)->post($this->url($package), $this->credentials() + ['revision' => 1])->assertRedirect(route('crew-bids.index'));

        $this->assertSame(['F5', 'R6', 'Q7'], $workspace->fresh()->selections);
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
    }

    public function test_guest_unverified_and_ungranted_users_cannot_sync(): void
    {
        $package = $this->package();
        $http = $this->portal();
        $this->get($this->url($package))->assertRedirect(route('login'));
        $this->post($this->url($package), $this->credentials() + ['revision' => 0])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->crewBidsAccess()->create())->post($this->url($package), $this->credentials() + ['revision' => 0])
            ->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->create())->post($this->url($package), $this->credentials() + ['revision' => 0])->assertForbidden();

        $http->assertNothingSent();
        $this->assertDatabaseCount('bid_workspaces', 0);
    }

    public function test_unpublished_and_archived_packages_cannot_be_synced(): void
    {
        [$user, $package, $workspace] = $this->workspace(['F1']);
        $http = $this->portal();
        $package->update(['published_at' => null]);
        $this->actingAs($user)->get($this->url($package))->assertNotFound();
        $this->post($this->url($package), $this->credentials() + ['revision' => 1, 'overwrite' => 1])->assertNotFound();
        $package->update(['published_at' => now(), 'archived_at' => now()]);
        $this->get($this->url($package))->assertNotFound();
        $this->post($this->url($package), $this->credentials() + ['revision' => 1, 'overwrite' => 1])->assertNotFound();

        $this->assertSame(['F1'], $workspace->fresh()->selections);
        $http->assertNothingSent();
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

    /** @param list<string> $selections
     * @return array{User, BidPackage, BidWorkspace}
     */
    private function workspace(array $selections): array
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $workspace = BidWorkspace::factory()->create(['user_id' => $user->id, 'bid_package_id' => $package->id, 'revision' => 1, 'selections' => $selections]);

        return [$user, $package, $workspace];
    }

    /** @return array{employee: string, portal_password: string} */
    private function credentials(): array
    {
        return ['employee' => '900000', 'portal_password' => ' synthetic-password '];
    }

    private function url(BidPackage $package): string
    {
        return route('crew-bids.submissions.sync.create', ['package' => $package, 'seat' => 'FO']);
    }

    private function portal(?string $sheet = null, ?Closure $duringRead = null): BidPortalHttpClient
    {
        $http = new BidPortalHttpClient;
        $http->preventStrayRequests();
        $this->app->instance(BidPortalHttpClient::class, $http);
        $http->fake([
            self::ORIGIN.'/' => fn (Request $request) => $request->method() === 'GET'
                ? $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html')))
                : $http->response('', 302, ['Location' => '/Pages/BidSheet.aspx']),
            self::ORIGIN.'/Pages/BidSheet.aspx' => $http->response($sheet ?? file_get_contents(base_path('tests/Fixtures/crew-bids/portal-open-bid-sheet.html'))),
            self::ORIGIN.'/Pages/BidLineDataOutput.aspx' => $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-submitted-report.html'))),
            self::ORIGIN.'/Reserved.ReportViewerWebControl.axd?*' => function () use ($http, $duringRead) {
                $content = $duringRead === null ? null : $duringRead();

                return $http->response($content ?? file_get_contents(base_path('tests/Fixtures/crew-bids/submitted-before.pdf')));
            },
        ]);

        return $http;
    }
}
