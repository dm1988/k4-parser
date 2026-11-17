<?php

namespace Tests\Feature;

use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\BidSubmission;
use App\Models\BidWorkspace;
use App\Models\CrewBidCredentialVault;
use App\Models\SubmittedBidSnapshot;
use App\Models\User;
use App\Services\CrewBids\BidPortalHttpClient;
use App\Services\CrewBids\CrewBidSecretStore;
use App\Services\CrewBids\CrewBidVaultService;
use App\Services\CrewBids\ResolveSubmittedBidOrder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Fixtures\InMemoryCrewBidSecretStore;
use Tests\TestCase;

class CrewBidSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private const string ORIGIN = 'https://crewbids.kalittaair.com';

    private int $writeAttempts = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(CrewBidSecretStore::class, new InMemoryCrewBidSecretStore);
    }

    public function test_saved_credentials_still_require_exact_order_review_and_explicit_final_confirmation(): void
    {
        [$user, $package] = $this->workspace();
        config(['crew_bids.vault.enabled' => true]);
        CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->post(route('crew-bids.connection.unlock'), ['app_password' => 'password'])->assertRedirect()->assertSessionMissing('crew_bid_sync_error');
        $this->withCookie(config('session.cookie'), session()->getId());
        $http = $this->portal();

        $this->post($this->createUrl($package), ['use_saved_credentials' => 1, 'revision' => 1])->assertRedirect()->assertSessionMissing('bid_submission_error');
        $submission = BidSubmission::query()->sole();
        $this->assertSame('reviewed', $submission->status);
        $this->post(route('crew-bids.submissions.submit', $submission), ['use_saved_credentials' => 1])->assertSessionHas('bid_submission_error');
        $this->assertSame(0, $this->writeAttempts);

        $this->post(route('crew-bids.submissions.submit', $submission), ['use_saved_credentials' => 1, 'confirmed' => 1])->assertRedirect();

        $this->assertSame('matched', $submission->fresh()->status);
        $this->assertSame(1, $this->writeAttempts);
        $this->assertCount(1, $this->writes($http));
    }

    public function test_lock_during_final_preflight_prevents_the_portal_update(): void
    {
        [$user, $package] = $this->workspace();
        config(['crew_bids.vault.enabled' => true]);
        CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->post(route('crew-bids.connection.unlock'), ['app_password' => 'password'])->assertRedirect();
        $this->withCookie(config('session.cookie'), session()->getId());
        $cancel = false;
        $reads = 0;
        $http = $this->portal(onRead: function () use (&$cancel, &$reads): void {
            if ($cancel && ++$reads === 2) {
                app(CrewBidVaultService::class)->lock(session()->driver());
                session()->save();
            }
        });
        $this->post($this->createUrl($package), ['use_saved_credentials' => 1, 'revision' => 1])->assertRedirect();
        $submission = BidSubmission::query()->sole();
        $cancel = true;

        $this->post(route('crew-bids.submissions.submit', $submission), ['use_saved_credentials' => 1, 'confirmed' => 1])->assertSessionHas('bid_submission_error');

        $this->assertSame(0, $this->writeAttempts);
        $this->assertCount(0, $this->writes($http));
        $this->assertNull($submission->fresh()->attempted_at);
    }

    public function test_guest_requests_cannot_start_the_portal_flow(): void
    {
        [$user, $package] = $this->workspace();
        $http = $this->portal();

        $this->get($this->createUrl($package))->assertRedirect(route('login'));
        $this->post($this->createUrl($package), $this->credentials() + ['revision' => 1])->assertRedirect(route('login'));

        $http->assertNothingSent();
        $this->assertDatabaseCount('bid_submissions', 0);
    }

    public function test_owner_compares_saved_choices_then_deliberately_submits_once_without_retaining_credentials(): void
    {
        [$user, $package, $workspace] = $this->workspace();
        Mail::fake();
        Notification::fake();
        $http = $this->portal();
        $this->actingAs($user)->get($this->createUrl($package))->assertOk()->assertSee(['Submit to Kalitta', 'F1', 'R2']);

        $response = $this->post($this->createUrl($package), $this->credentials() + ['revision' => 1]);

        $submission = BidSubmission::query()->sole();
        $response->assertRedirect(route('crew-bids.submissions.show', $submission));
        $this->assertSame('reviewed', $submission->status);
        $this->assertSame(['F1', 'R2'], $submission->review['selections']);
        $this->assertNull($submission->attempted_at);
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        $this->get(route('crew-bids.submissions.show', $submission))->assertOk()->assertSee(['Current portal report', 'Replace with this exact order', 'Submit this bid to Kalitta'])
            ->assertDontSee('900000')->assertDontSee('synthetic-password');

        $this->post(route('crew-bids.submissions.submit', $submission), $this->credentials() + ['confirmed' => 1])->assertRedirect();

        $this->assertSame('matched', $submission->fresh()->status);
        $this->assertNotNull($submission->fresh()->attempted_at);
        $this->assertSame(['F1' => 1, 'R2' => 2], app(ResolveSubmittedBidOrder::class)->resolve($user, $package, 'FO')->ranks);
        $this->assertSame([1, 2], SubmittedBidSnapshot::query()->sole()->evidence['numbers']);
        $this->assertSame(['F1', 'R2'], $workspace->fresh()->selections);
        $this->get(route('crew-bids.submissions.show', $submission))->assertOk()->assertSee('fresh Kalitta report matches every choice')
            ->assertSee('This verifies the order in Kalitta\'s submitted report.', false)
            ->assertSee('Awarded lines are checked separately in the portal.')
            ->assertDontSee('Bid Received')->assertDontSee('Receipt email')->assertDontSee('Submit this bid to Kalitta');
        $this->post(route('crew-bids.submissions.submit', $submission), $this->credentials() + ['confirmed' => 1])->assertRedirect();
        $this->assertCount(1, $this->writes($http));
        $stored = DB::table('bid_submissions')->where('id', $submission->id)->value('review');
        $this->assertStringNotContainsString('900000', $stored);
        $this->assertStringNotContainsString('synthetic-password', $stored);
        $this->assertStringNotContainsString('selections', $stored);
        $this->assertArrayNotHasKey('review', $submission->toArray());
        $session = json_encode(session()->all(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('900000', $session);
        $this->assertStringNotContainsString('synthetic-password', $session);
        $this->assertFalse(config('debugbar.enabled'));
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    public function test_final_confirmation_and_fresh_credentials_are_required_before_any_bid_update(): void
    {
        [$user, $package] = $this->workspace();
        $http = $this->portal();
        $this->actingAs($user)->post($this->createUrl($package), $this->credentials() + ['revision' => 1]);
        $submission = BidSubmission::query()->sole();

        $this->post(route('crew-bids.submissions.submit', $submission), $this->credentials())->assertRedirect(route('crew-bids.submissions.show', $submission))->assertSessionHas('bid_submission_error');
        $this->post(route('crew-bids.submissions.submit', $submission), ['confirmed' => 1])->assertSessionHas('bid_submission_error');

        $this->assertSame('reviewed', $submission->fresh()->status);
        $this->assertCount(0, $this->writes($http));
    }

    public function test_unknown_attempt_blocks_another_submission_until_a_read_only_reconciliation(): void
    {
        [$user, $package, $workspace] = $this->workspace();
        $http = $this->portal(timeout: true);
        $this->actingAs($user)->post($this->createUrl($package), $this->credentials() + ['revision' => 1]);
        $submission = BidSubmission::query()->sole();

        $this->post(route('crew-bids.submissions.submit', $submission), $this->credentials() + ['confirmed' => 1])->assertRedirect();

        $this->assertSame('unknown', $submission->fresh()->status);
        $this->get(route('crew-bids.submissions.show', $submission))->assertOk()->assertSee(['Submission status unknown', 'Check portal report only'])->assertDontSee('Submit this bid to Kalitta');
        $this->post($this->createUrl($package), $this->credentials() + ['revision' => 1])->assertSessionHas('bid_submission_error');
        $this->assertDatabaseCount('bid_submissions', 1);
        $this->post(route('crew-bids.submissions.reconcile', $submission), $this->credentials())->assertRedirect();
        $this->assertSame('matched', $submission->fresh()->status);
        $this->assertSame([1, 2], SubmittedBidSnapshot::query()->sole()->evidence['numbers']);
        $this->assertSame(1, $this->writeAttempts);
        $this->assertSame(['F1', 'R2'], $workspace->fresh()->selections);
    }

    public function test_a_changed_workspace_cannot_be_submitted_from_an_older_review(): void
    {
        [$user, $package, $workspace] = $this->workspace();
        $http = $this->portal();
        $this->actingAs($user)->post($this->createUrl($package), $this->credentials() + ['revision' => 1]);
        $submission = BidSubmission::query()->sole();
        $workspace->update(['revision' => 2, 'selections' => ['R2', 'F1']]);

        $this->post(route('crew-bids.submissions.submit', $submission), $this->credentials() + ['confirmed' => 1])->assertSessionHas('bid_submission_error');

        $this->assertCount(0, $this->writes($http));
        $this->assertSame(['R2', 'F1'], $workspace->fresh()->selections);
        $this->assertSame('reviewed', $submission->fresh()->status);
    }

    public function test_expired_review_and_different_portal_account_cannot_send_a_bid(): void
    {
        [$user, $package] = $this->workspace();
        $http = $this->portal();
        $this->actingAs($user)->post($this->createUrl($package), $this->credentials() + ['revision' => 1]);
        $submission = BidSubmission::query()->sole();
        $this->post(route('crew-bids.submissions.submit', $submission), ['employee' => '900001', 'portal_password' => 'synthetic-password', 'confirmed' => 1])->assertSessionHas('bid_submission_error');
        $this->travel(11)->minutes();

        $this->post(route('crew-bids.submissions.submit', $submission), $this->credentials() + ['confirmed' => 1])->assertSessionHas('bid_submission_error');

        $this->assertCount(0, $this->writes($http));
        $this->assertNull($submission->fresh()->attempted_at);
    }

    public function test_other_users_cannot_read_or_submit_a_review_or_use_another_owners_workspace(): void
    {
        [$user, $package] = $this->workspace();
        $http = $this->portal();
        $this->actingAs($user)->post($this->createUrl($package), $this->credentials() + ['revision' => 1]);
        $submission = BidSubmission::query()->sole();
        $this->actingAs(User::factory()->crewBidsAccess()->create());

        $this->get($this->createUrl($package))->assertNotFound();
        $this->get(route('crew-bids.submissions.show', $submission))->assertNotFound();
        $this->post(route('crew-bids.submissions.submit', $submission), $this->credentials() + ['confirmed' => 1])->assertNotFound();
        $this->post(route('crew-bids.submissions.reconcile', $submission), $this->credentials())->assertNotFound();

        $this->assertCount(0, $this->writes($http));
        $this->assertSame('reviewed', $submission->fresh()->status);
    }

    public function test_disabled_feature_and_revoked_access_block_the_flow(): void
    {
        [$user, $package] = $this->workspace();
        $http = $this->portal();
        config(['crew_bids.submission.enabled' => false]);
        $this->actingAs($user)->get($this->createUrl($package))->assertNotFound();
        config(['crew_bids.submission.enabled' => true]);
        $user->update(['crew_bids_access' => false]);
        $this->get($this->createUrl($package))->assertForbidden();
        $this->post($this->createUrl($package), $this->credentials() + ['revision' => 1])->assertForbidden();
        $this->actingAs(User::factory()->unverified()->crewBidsAccess()->create())->get($this->createUrl($package))->assertRedirect(route('verification.notice'));

        $http->assertNothingSent();
        $this->assertDatabaseCount('bid_submissions', 0);
    }

    public function test_a_new_published_package_blocks_an_obsolete_workspace_before_contacting_the_portal(): void
    {
        [$user, $package] = $this->workspace();
        $http = $this->portal();
        BidPackage::factory()->create(['period' => $package->period, 'fleet' => $package->fleet]);

        $this->actingAs($user)->post($this->createUrl($package), $this->credentials() + ['revision' => 1])->assertSessionHas('bid_submission_error');

        $http->assertNothingSent();
        $this->assertDatabaseCount('bid_submissions', 0);
    }

    public function test_using_one_review_supersedes_other_review_tokens_for_the_owner(): void
    {
        [$user, $package] = $this->workspace();
        $http = $this->portal();
        $this->actingAs($user)->post($this->createUrl($package), $this->credentials() + ['revision' => 1]);
        $first = BidSubmission::query()->sole();
        $this->post($this->createUrl($package), $this->credentials() + ['revision' => 1]);
        $second = BidSubmission::query()->whereKeyNot($first->id)->sole();

        $this->post(route('crew-bids.submissions.submit', $first), $this->credentials() + ['confirmed' => 1])->assertRedirect();
        $this->post(route('crew-bids.submissions.submit', $second), $this->credentials() + ['confirmed' => 1])->assertSessionHas('bid_submission_error');

        $this->assertSame('stale', $second->fresh()->status);
        $this->assertCount(1, $this->writes($http));
    }

    public function test_credentials_validation_never_flashes_input_and_the_review_button_uses_a_native_form(): void
    {
        [$user, $package] = $this->workspace();
        $http = $this->portal();
        $this->actingAs($user)->post($this->createUrl($package), ['employee' => 'invalid-private-employee', 'portal_password' => 'synthetic-password', 'revision' => 1])->assertRedirect($this->createUrl($package))->assertSessionHas('bid_submission_error')->assertSessionMissing('_old_input');
        $http->assertNothingSent();
        Livewire::actingAs($user)->test(CrewBids::class)->set('showList', true)->set('showReview', true)
            ->assertSee('Submit to Kalitta')->assertSee(route('crew-bids.submissions.create', ['package' => $package, 'seat' => 'FO']), false);
    }

    public function test_portal_credentials_start_blank_and_require_focus_before_entry(): void
    {
        [$user, $package] = $this->workspace();
        $response = $this->actingAs($user)->get($this->createUrl($package))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame(2, substr_count($response->getContent(), '@focus="$el.removeAttribute(\'readonly\')"'));

        foreach (['employee' => 'off', 'portal_password' => 'new-password'] as $name => $autocomplete) {
            $input = $xpath->query('//input[@name="'.$name.'"]')->item(0);
            $this->assertNotNull($input);
            $this->assertSame('', $input->getAttribute('value'));
            $this->assertTrue($input->hasAttribute('readonly'));
            $this->assertSame($autocomplete, $input->getAttribute('autocomplete'));
            $this->assertTrue($input->hasAttribute('data-1p-ignore'));
            $this->assertSame('true', $input->getAttribute('data-lpignore'));
        }
    }

    /** @return array{User, BidPackage, BidWorkspace} */
    private function workspace(): array
    {
        config(['crew_bids.submission.enabled' => true]);
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $workspace = BidWorkspace::factory()->create(['user_id' => $user->id, 'bid_package_id' => $package->id, 'revision' => 1, 'selections' => ['F1', 'R2']]);

        return [$user, $package, $workspace];
    }

    /** @return array{employee: string, portal_password: string} */
    private function credentials(): array
    {
        return ['employee' => '900000', 'portal_password' => ' synthetic-password '];
    }

    private function createUrl(BidPackage $package): string
    {
        return route('crew-bids.submissions.create', ['package' => $package, 'seat' => 'FO']);
    }

    private function writes(BidPortalHttpClient $http): Collection
    {
        return $http->recorded(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
    }

    private function portal(bool $timeout = false, ?\Closure $onRead = null): BidPortalHttpClient
    {
        $http = new BidPortalHttpClient;
        $http->preventStrayRequests();
        $this->app->instance(BidPortalHttpClient::class, $http);
        $written = false;
        $sheet = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-open-bid-sheet.html'));
        $http->fake([
            self::ORIGIN.'/' => fn (Request $request) => $request->method() === 'GET'
                ? $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html')))
                : $http->response('', 302, ['Location' => '/Pages/BidSheet.aspx']),
            self::ORIGIN.'/Pages/BidSheet.aspx' => function (Request $request) use ($http, $sheet, $timeout, $onRead, &$written) {
                if ($request->method() === 'POST') {
                    $this->writeAttempts++;
                    $written = true;
                    if ($timeout) {
                        throw new ConnectionException('synthetic-password upstream response');
                    }

                    return $http->response('Update returned without an acceptance label.');
                }

                $onRead?->__invoke();

                return $http->response($written ? str_replace(['value="5"', 'value="6"', 'value="7"'], ['value="1"', 'value="2"', 'value=""'], $sheet) : $sheet);
            },
            self::ORIGIN.'/Pages/BidLineDataOutput.aspx' => $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-submitted-report.html'))),
            self::ORIGIN.'/Reserved.ReportViewerWebControl.axd?*' => function () use ($http, &$written) {
                return $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/submitted-'.($written ? 'after' : 'before').'.pdf')));
            },
        ]);

        return $http;
    }
}
