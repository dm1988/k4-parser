<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\DTOs\CrewBidBrowseCriteria;
use App\DTOs\CrewBidsPageData;
use App\Enums\CrewBidSort;
use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\BidSubmission;
use App\Models\BidWorkspace;
use App\Models\User;
use App\Services\CrewBids\ResolveSubmittedBidOrder;
use App\View\Models\CrewBidsPageDataFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrewBidSubmittedOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_ranks_are_independent_of_draft_ranks_and_sorting_is_read_only(): void
    {
        Http::preventStrayRequests();
        $workspace = $this->workspace();
        $submission = $this->matched($workspace);
        $original = $workspace->getAttributes();
        $review = $submission->review;

        $data = $this->page($workspace);

        $this->assertSame(['F10', 'F3', 'F1', 'F20'], array_column($data->lineCards, 'id'));
        $this->assertSame([2, 5, null, null], array_column($data->lineCards, 'submittedRank'));
        $this->assertSame([null, 1, 2, null], array_column($data->lineCards, 'rank'));
        $this->assertSame("1. F3\n2. F1", $data->copyText);
        $this->assertStringContainsString("1 3\n2 1", $data->aiPrompt);
        $this->assertSame('2026-11-15 12:00:00', $data->submittedOrder->checkedAt->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($original, $workspace->fresh()->getAttributes());
        $this->assertSame($review, $submission->fresh()->review);
        $this->assertSame('matched', $submission->fresh()->status);
        $this->assertDatabaseCount('bid_submissions', 1);
        Http::assertNothingSent();
    }

    public function test_filters_and_tabs_keep_rank_gaps_and_exclude_missing_or_ineligible_lines(): void
    {
        $workspace = $this->workspace();
        $this->matched($workspace);

        $filtered = $this->page($workspace, minimumCredit: 70);
        $reserve = $this->page($workspace, tab: 'reserve');
        $selected = $this->page($workspace, selectedOnly: true);

        $this->assertSame(['F3'], array_column($filtered->lineCards, 'id'));
        $this->assertSame(5, $filtered->lineCards[0]->submittedRank);
        $this->assertSame(['R2'], array_column($reserve->lineCards, 'id'));
        $this->assertSame(1, $reserve->lineCards[0]->submittedRank);
        $this->assertSame(['F3', 'F1'], array_column($selected->lineCards, 'id'));
        $this->assertArrayNotHasKey('F4', $reserve->browse->allLines);
        $this->assertArrayNotHasKey('F99', $reserve->browse->allLines);
    }

    public function test_other_sorts_hide_submitted_ranks_and_keep_their_existing_order(): void
    {
        $workspace = $this->workspace();
        $this->matched($workspace);

        $data = $this->page($workspace, sort: CrewBidSort::LineId);

        $this->assertSame(['F1', 'F3', 'F10', 'F20'], array_column($data->lineCards, 'id'));
        $this->assertSame([null, null, null, null], array_column($data->lineCards, 'submittedRank'));
    }

    /** @param array<string, mixed> $attributes
     * @param array<string, mixed> $reviewOverrides
     */
    #[DataProvider('unusableEvidence')]
    public function test_unusable_evidence_does_not_establish_submitted_order(array $attributes, array $reviewOverrides): void
    {
        $workspace = $this->workspace();
        $submission = $this->matched($workspace, $attributes);
        $review = $submission->review;
        foreach ($reviewOverrides as $key => $value) {
            Arr::set($review, $key, $value);
        }
        $submission->update(['review' => $review]);

        $order = app(ResolveSubmittedBidOrder::class)->resolve(User::findOrFail($workspace->user_id), BidPackage::findOrFail($workspace->bid_package_id), 'FO');

        $this->assertFalse($order->available());
        $this->assertSame([], $order->ranks);
        $this->assertNull($order->checkedAt);
    }

    /** @return array<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function unusableEvidence(): array
    {
        return [
            'review only' => [['status' => 'reviewed', 'attempted_at' => null], []],
            'stale review' => [['status' => 'stale', 'attempted_at' => null], []],
            'unknown attempt' => [['status' => 'unknown'], []],
            'nonmatching report' => [['status' => 'not_matched'], []],
            'unchecked report' => [['reconciled_at' => null], []],
            'no attempt' => [['attempted_at' => null], []],
            'check predates attempt' => [['reconciled_at' => '2026-11-15 11:00:00'], []],
            'period mismatch' => [[], ['context.period' => '2026-12']],
            'fleet mismatch' => [[], ['context.fleet' => '747']],
            'seat mismatch' => [[], ['context.seat' => 'CA']],
            'unbound account' => [[], ['context.account_matches_input' => false]],
            'missing package context' => [[], ['context.package_id' => null]],
            'missing portal revision' => [[], ['context.package_revision' => null]],
            'local revision changed' => [[], ['local_revision' => 'Different release']],
            'fingerprint changed' => [[], ['local_package_fingerprint' => 'Different package']],
            'payload changed' => [[], ['local_payload_hash' => 'Different payload']],
            'duplicate IDs' => [[], ['selections' => ['F1', 'F1'], 'numbers' => [1, 1]]],
            'duplicate portal numbers' => [[], ['selections' => ['F1', 'R1'], 'numbers' => [1, 1]]],
            'unmapped ID' => [[], ['selections' => ['X1'], 'numbers' => [1]]],
            'mismatched report numbers' => [[], ['numbers' => [1, 2, 3]]],
            'empty order' => [[], ['selections' => [], 'numbers' => []]],
            'non-list order' => [[], ['selections' => [1 => 'F1']]],
        ];
    }

    #[DataProvider('blockingAttempts')]
    public function test_newer_attempts_prevent_reusing_an_older_match(string $status): void
    {
        $workspace = $this->workspace();
        $old = $this->matched($workspace);
        $this->matched($workspace, ['status' => $status, 'attempted_at' => now()->addMinute(), 'reconciled_at' => null]);
        $old->update(['reconciled_at' => now()->addMinutes(2)]);

        $order = app(ResolveSubmittedBidOrder::class)->resolve(User::findOrFail($workspace->user_id), BidPackage::findOrFail($workspace->bid_package_id), 'FO');

        $this->assertFalse($order->available());
    }

    /** @return array<string, array{string}> */
    public static function blockingAttempts(): array
    {
        return ['unknown' => ['unknown'], 'nonmatching' => ['not_matched'], 'stale attempted review' => ['stale']];
    }

    public function test_newest_match_wins_while_unattempted_reviews_do_not_replace_it(): void
    {
        $workspace = $this->workspace();
        $this->matched($workspace, ['attempted_at' => now()->subHour()]);
        $new = $this->matched($workspace);
        $review = $new->review;
        $review['selections'] = ['F3', 'F10'];
        $review['numbers'] = [3, 10];
        $new->update(['review' => $review]);
        $this->matched($workspace, ['status' => 'reviewed', 'attempted_at' => null, 'created_at' => now()->addMinute()]);

        $order = app(ResolveSubmittedBidOrder::class)->resolve(User::findOrFail($workspace->user_id), BidPackage::findOrFail($workspace->bid_package_id), 'FO');

        $this->assertSame(['F3' => 1, 'F10' => 2], $order->ranks);
    }

    public function test_sources_are_isolated_by_user_package_seat_and_record_owner(): void
    {
        $workspace = $this->workspace();
        $submission = $this->matched($workspace);
        $user = User::findOrFail($workspace->user_id);
        $other = User::factory()->admin()->create();
        $package = BidPackage::findOrFail($workspace->bid_package_id);
        $resolver = app(ResolveSubmittedBidOrder::class);

        $this->assertFalse($resolver->resolve($other, $package, 'FO')->available());
        $this->assertFalse($resolver->resolve($user, BidPackage::factory()->create(), 'FO')->available());
        $this->assertFalse($resolver->resolve($user, $package, 'CA')->available());
        $submission->update(['user_id' => $other->id]);
        $this->assertFalse($resolver->resolve($user, $package, 'FO')->available());
    }

    public function test_livewire_shows_source_and_separate_ranks_without_saving_or_contacting_the_portal(): void
    {
        Http::preventStrayRequests();
        $workspace = $this->workspace();
        $this->matched($workspace);

        $component = Livewire::actingAs(User::findOrFail($workspace->user_id))->test(CrewBids::class)
            ->set('sort', 'submitted')->assertHasNoErrors()
            ->assertSee('Submitted #2')->assertSee('Selected #1')
            ->assertSee('2026-11-15 12:00:00 UTC')->assertSet('selections', ['F3', 'F1']);

        $this->assertSame(['F10', 'F3', 'F1', 'F20'], array_column($component->viewData('lineCards'), 'id'));
        $this->assertSame(7, $workspace->fresh()->revision);
        $this->assertSame(['F3', 'F1'], $workspace->fresh()->selections);
        Http::assertNothingSent();
    }

    public function test_add_matching_appends_in_submitted_display_order_and_preserves_existing_ranks(): void
    {
        $workspace = $this->workspace();
        $this->matched($workspace);

        Livewire::actingAs(User::findOrFail($workspace->user_id))->test(CrewBids::class)
            ->set('sort', 'submitted')->call('addMatching')->assertHasNoErrors()
            ->assertSet('selections', ['F3', 'F1', 'F10', 'F20']);

        $this->assertSame(['F3', 'F1', 'F10', 'F20'], $workspace->fresh()->selections);
    }

    public function test_missing_source_disables_option_and_resets_a_client_selected_submitted_sort(): void
    {
        $workspace = $this->workspace();

        Livewire::actingAs(User::findOrFail($workspace->user_id))->test(CrewBids::class)
            ->assertSee('value="submitted" disabled', false)
            ->assertSee('No confirmed submitted order available')
            ->set('sort', 'submitted')->assertSet('sort', 'best')
            ->assertSee('No confirmed submitted order available. Switched to Best match.');

        $this->assertSame(7, $workspace->fresh()->revision);
    }

    public function test_context_switch_and_new_uncertain_attempt_reset_sort_with_a_notice(): void
    {
        $workspace = $this->workspace();
        $this->matched($workspace);
        $otherPackage = BidPackage::factory()->create(['period' => '2026-12']);
        $component = Livewire::actingAs(User::findOrFail($workspace->user_id))->test(CrewBids::class)->set('sort', 'submitted');

        $component->set('contextPackage', (string) $otherPackage->id)->call('changeContext')
            ->assertSet('packageId', $otherPackage->id)->assertSet('sort', 'best')
            ->assertSee('No confirmed submitted order available. Switched to Best match.');
        $component->set('contextPackage', (string) $workspace->bid_package_id)->call('changeContext')->set('sort', 'submitted');
        $this->matched($workspace, ['status' => 'unknown', 'attempted_at' => now()->addMinute(), 'reconciled_at' => null]);
        $component->call('$refresh')->assertSet('sort', 'best')
            ->assertSee('No confirmed submitted order available. Switched to Best match.');
    }

    private function workspace(): BidWorkspace
    {
        $this->travelTo('2026-11-15 12:00:00');
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        foreach (['F3', 'F4', 'F10', 'F20'] as $id) {
            $payload['lines'][$id] = array_replace($payload['lines']['F1'], ['id' => $id]);
        }
        $payload['lines']['F3']['credit'] = 4500;
        $payload['lines']['F4']['first_officers'] = 0;
        $package->update(['payload' => $payload]);

        return BidWorkspace::factory()->create(['user_id' => User::factory()->admin(), 'bid_package_id' => $package->id,
            'revision' => 7, 'selections' => ['F3', 'F1']]);
    }

    /** @param array<string, mixed> $attributes */
    private function matched(BidWorkspace $workspace, array $attributes = []): BidSubmission
    {
        $package = BidPackage::findOrFail($workspace->bid_package_id);

        return BidSubmission::factory()->create(array_replace([
            'user_id' => $workspace->user_id, 'bid_workspace_id' => $workspace->id, 'status' => 'matched',
            'attempted_at' => now()->subMinute(), 'reconciled_at' => now(),
            'review' => ['context' => ['account_matches_input' => true, 'period' => $package->period, 'fleet' => $package->fleet,
                'seat' => $workspace->seat, 'package_id' => 4000, 'package_revision' => 1],
                'local_revision' => $package->source_revision, 'local_package_fingerprint' => $package->fingerprint,
                'local_payload_hash' => hash('sha256', json_encode($package->payload, JSON_THROW_ON_ERROR)),
                'selections' => ['R2', 'F10', 'F99', 'F4', 'F3'], 'numbers' => [2, 10, 99, 4, 3]],
        ], $attributes));
    }

    private function page(BidWorkspace $workspace, string $tab = 'flying', ?int $minimumCredit = null, bool $selectedOnly = false, CrewBidSort $sort = CrewBidSort::Submitted): CrewBidsPageData
    {
        return app(CrewBidsPageDataFactory::class)->make(BidPackage::findOrFail($workspace->bid_package_id), User::findOrFail($workspace->user_id), new CrewBidBrowseCriteria(
            seat: $workspace->seat, preferences: $workspace->preferences, selections: $workspace->selections,
            tab: $tab, sort: $sort, minimumCredit: $minimumCredit, selectedOnly: $selectedOnly,
        ));
    }
}
