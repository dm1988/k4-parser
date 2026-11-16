<?php

namespace Tests\Feature;

use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\CrewBidSnapshot;
use App\Models\PilotSeniorityList;
use App\Models\User;
use App\Services\CrewBids\PredictCrewBidLines;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrewBidPredictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_senior_choices_cascade_and_each_bidder_consumes_only_one_position(): void
    {
        $package = $this->package();
        $snapshot = CrewBidSnapshot::factory()->create(['bidders' => [
            '1' => ['name' => 'First', 'seniority' => 10, 'numbers' => [1, 2]],
            '2' => ['name' => 'Second', 'seniority' => 20, 'numbers' => [1, 2]],
            '3' => ['name' => 'Third', 'seniority' => 30, 'numbers' => [1, 2, 3]],
            '4' => ['name' => 'Viewer', 'seniority' => 40, 'numbers' => [1, 2, 3]],
            '5' => ['name' => 'Junior', 'seniority' => 50, 'numbers' => [3]],
        ]]);

        $result = app(PredictCrewBidLines::class)->predict($package, $snapshot, 'FO', '4', ['F1', 'R2', 'F3']);

        $this->assertTrue($result['available']);
        $this->assertSame('F3', $result['likely']);
        $this->assertSame('unavailable', $result['lines']['F1']['status']);
        $this->assertSame(0, $result['lines']['F1']['remaining']);
        $this->assertSame(['First', 'Second'], array_column($result['lines']['F1']['occupants'], 'name'));
        $this->assertSame(['Third'], array_column($result['lines']['R2']['occupants'], 'name'));
        $this->assertSame(1, $result['lines']['F3']['remaining']);
        $this->assertSame([], $result['lines']['F3']['occupants']);
        $this->assertSame(['Third', 'Viewer', 'Junior'], array_column($result['lines']['F3']['bidders'], 'name'));
    }

    public function test_senior_interest_alone_does_not_make_a_line_unavailable(): void
    {
        $package = $this->package();
        $snapshot = CrewBidSnapshot::factory()->create();

        $result = app(PredictCrewBidLines::class)->predict($package, $snapshot, 'FO', '910003', ['R2', 'F1']);

        $this->assertSame('R2', $result['likely']);
        $this->assertSame([], $result['lines']['R2']['occupants']);
        $this->assertCount(3, $result['lines']['R2']['bidders']);
        $this->assertSame(1, $result['lines']['R2']['remaining']);
    }

    public function test_confirmed_portal_rank_gaps_preserve_rank_labels_and_seniority_allocation(): void
    {
        $package = $this->package();
        $snapshot = CrewBidSnapshot::factory()->create(['bidders' => [
            '1' => ['name' => 'First', 'seniority' => 10, 'numbers' => [1]],
            '2' => ['name' => 'Second', 'seniority' => 20, 'numbers' => [1]],
            '3' => ['name' => 'Third', 'seniority' => 30, 'numbers' => [1, 2, 3], 'ranks' => [1, 4, 7]],
            '4' => ['name' => 'Viewer', 'seniority' => 40, 'numbers' => [1, 2, 3]],
        ]]);

        $result = app(PredictCrewBidLines::class)->predict($package, $snapshot, 'FO', '4', ['F3']);

        $this->assertTrue($result['available']);
        $this->assertSame('F3', $result['likely']);
        $this->assertSame(['Third'], array_column($result['lines']['R2']['occupants'], 'name'));
        $this->assertSame([4], array_column($result['lines']['R2']['occupants'], 'rank'));
        $this->assertSame([4, 2], array_column($result['lines']['R2']['bidders'], 'rank'));
        $this->assertSame([1, 4, 7], $snapshot->fresh()->bidders[3]['ranks']);
    }

    public function test_repeated_line_choices_do_not_consume_extra_awards(): void
    {
        $package = $this->package();
        $payload = $package->payload;
        $payload['lines']['F1']['first_officers'] = 1;
        $package->update(['payload' => $payload]);
        $snapshot = CrewBidSnapshot::factory()->create(['bidders' => [
            '1' => ['name' => 'First', 'seniority' => 1, 'numbers' => [1, 1, 2]],
            '2' => ['name' => 'Second', 'seniority' => 2, 'numbers' => [1, 1, 2]],
            '3' => ['name' => 'Viewer', 'seniority' => 3, 'numbers' => [1, 2, 3]],
        ]]);

        $result = app(PredictCrewBidLines::class)->predict($package, $snapshot, 'FO', '3', ['F1', 'R2', 'F3']);

        $this->assertSame('F3', $result['likely']);
        $this->assertSame(['First'], array_column($result['lines']['F1']['occupants'], 'name'));
        $this->assertSame(['Second'], array_column($result['lines']['R2']['occupants'], 'name'));
        $this->assertCount(3, $result['lines']['F1']['bidders']);
    }

    public function test_seat_capacity_and_submitted_order_are_used_when_the_private_list_is_empty(): void
    {
        $package = $this->package();
        $snapshot = CrewBidSnapshot::factory()->create(['seat' => 'CA']);

        $result = app(PredictCrewBidLines::class)->predict($package, $snapshot, 'CA', '910002', []);

        $this->assertSame('R2', $result['likely']);
        $this->assertSame('Your submitted order', $result['basis']);
        $this->assertSame(1, $result['lines']['F1']['capacity']);
    }

    public function test_unknown_senior_choice_blocks_predictions_instead_of_falsely_freeing_capacity(): void
    {
        $package = $this->package();
        $snapshot = CrewBidSnapshot::factory()->create(['bidders' => [
            '1' => ['name' => 'Senior', 'seniority' => 1, 'numbers' => [99, 1]],
            '2' => ['name' => 'Viewer', 'seniority' => 2, 'numbers' => [1, 2]],
        ]]);

        $result = app(PredictCrewBidLines::class)->predict($package, $snapshot, 'FO', '2', ['F1']);

        $this->assertFalse($result['available']);
        $this->assertNull($result['likely']);
        $this->assertStringContainsString('unknown or ambiguous', $result['reason']);
        $this->assertNull($result['lines']['F1']['remaining']);
    }

    public function test_ambiguous_line_numbers_and_duplicate_seniority_are_not_treated_as_awards(): void
    {
        $package = $this->package();
        $payload = $package->payload;
        $payload['lines']['Q1'] = array_replace($payload['lines']['F1'], ['id' => 'Q1']);
        $package->update(['payload' => $payload]);
        $snapshot = CrewBidSnapshot::factory()->create();
        $service = app(PredictCrewBidLines::class);
        $this->assertFalse($service->predict($package, $snapshot, 'FO', '910003', ['R2'])['available']);
        $snapshot->bidders = ['1' => ['name' => 'First', 'seniority' => 1, 'numbers' => [2]], '2' => ['name' => 'Second', 'seniority' => 1, 'numbers' => [2]]];
        $result = $service->predict($package, $snapshot, 'FO', '2', ['R2']);
        $this->assertFalse($result['available']);
        $this->assertStringContainsString('seniority is missing or ambiguous', $result['reason']);
    }

    public function test_stale_missing_context_and_unknown_bidder_are_explicit(): void
    {
        $this->freezeTime();
        $package = $this->package();
        $snapshot = CrewBidSnapshot::factory()->create(['retrieved_at' => now()->subMinutes(16)]);
        $service = app(PredictCrewBidLines::class);
        $result = $service->predict($package, $snapshot, 'FO', '910003', ['R2']);
        $this->assertTrue($result['stale']);
        $this->assertNotNull($result['checked_at']);
        $this->assertNull($service->predict($package, null, 'FO', '910003', ['R2'])['likely']);
        $this->assertFalse($service->predict($package, $snapshot, 'CA', '910003', ['R2'])['available']);
        $this->assertFalse($service->predict($package, $snapshot, 'FO', 'missing', ['R2'])['available']);
        $snapshot->period = '2026-12';
        $this->assertFalse($service->predict($package, $snapshot, 'FO', '910003', ['R2'])['available']);
    }

    public function test_unknown_earlier_private_choice_does_not_produce_an_unsupported_likely_hold(): void
    {
        $package = $this->package();
        $snapshot = CrewBidSnapshot::factory()->create();

        $result = app(PredictCrewBidLines::class)->predict($package, $snapshot, 'FO', '910003', ['F999', 'R2']);

        $this->assertNull($result['likely']);
        $this->assertStringContainsString('earlier choice', $result['reason']);
    }

    public function test_workspace_automatically_shows_only_the_current_users_prediction_without_changing_private_state(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910003', 'crew_bid_employee_verified_at' => now()])->save();
        PilotSeniorityList::factory()->create();
        $package = $this->package();
        $workspace = BidWorkspace::factory()->create(['user_id' => $user->id, 'bid_package_id' => $package->id, 'selections' => ['R2', 'F1'], 'revision' => 3]);
        $snapshot = CrewBidSnapshot::factory()->create();

        $component = Livewire::actingAs($user)->test(CrewBids::class)
            ->assertSeeText('Likely hold R2')->assertSee('Projected unavailable')->assertDontSee('Example, Avery')
            ->assertDontSee('Predict for bidder')->assertDontSee('Submitted bids for this line:');

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//header//button[@aria-controls="bid-predictions"]')->length);
        $this->assertSame(1, $xpath->query('//dialog[@id="bid-predictions"]//table')->length);
        $this->assertSame(3, $xpath->query('//dialog[@id="bid-predictions"]//tbody/tr')->length);
        $this->assertSame(0, $xpath->query('//article//p[contains(., "positions remain after senior projections")]')->length);
        $this->assertSame(1, $xpath->query('//article//span[@role="img" and contains(@aria-label, "Projected unavailable · 0/2")]')->length);

        $component->set('tab', 'reserve')->assertDontSee('choice #2');

        $this->assertSame(['R2', 'F1'], $workspace->fresh()->selections);
        $this->assertSame(3, $workspace->fresh()->revision);
        $this->assertDatabaseCount('bid_submissions', 0);
        $this->assertSame($snapshot->id, CrewBidSnapshot::query()->sole()->id);
    }

    public function test_prediction_pill_keeps_missing_link_and_stale_data_feedback_accessible_in_the_modal(): void
    {
        $this->freezeTime();
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        PilotSeniorityList::factory()->create();
        CrewBidSnapshot::factory()->create(['retrieved_at' => now()->subMinutes(16)]);

        $component = Livewire::actingAs($user)->test(CrewBids::class)
            ->assertSee('Unavailable')->assertSee('Stale')
            ->assertSee('Link your employee number once')
            ->assertSee('Employee link / shared data')
            ->assertSee('Update submitted bids')
            ->assertSee(route('crew-bids.employees.index', ['package' => $package, 'seat' => 'FO']).'#refresh-shared-bids', false)
            ->assertDontSee('Likely hold');

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//dialog[@id="bid-predictions"]//p[@id="bid-predictions-reason" and contains(., "Link your employee number once")]')->length);
        $this->assertSame(0, $xpath->query('//dialog[@id="bid-predictions"]//table')->length);
        $this->assertSame(0, $xpath->query('//article//span[@role="img" and contains(@aria-label, "Projected available")]')->length);
    }

    public function test_fresh_shared_bids_hide_the_stale_refresh_action_without_changing_the_private_list(): void
    {
        $this->freezeTime();
        $package = $this->package();
        $user = User::factory()->crewBidsAccess()->create();
        $workspace = BidWorkspace::factory()->create(['user_id' => $user->id, 'bid_package_id' => $package->id, 'selections' => ['F3', 'F1'], 'revision' => 3]);
        CrewBidSnapshot::factory()->create(['retrieved_at' => now()]);

        Livewire::actingAs($user)->test(CrewBids::class)->assertDontSee('Update submitted bids')->assertSet('selections', ['F3', 'F1']);

        $this->assertSame(['F3', 'F1'], $workspace->fresh()->selections);
        $this->assertSame(3, $workspace->fresh()->revision);
    }

    public function test_imported_seniority_updates_apply_by_employee_number_without_mutating_shared_bids(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910003', 'crew_bid_employee_verified_at' => now()])->save();
        $package = $this->package();
        $snapshot = CrewBidSnapshot::factory()->create();
        $before = $snapshot->fresh()->getRawOriginal();
        $list = PilotSeniorityList::factory()->create();
        $pilots = $list->pilots;
        $pilots['910003'] = ['name' => 'Renamed, Casey', 'seniority' => 1];
        $list->update(['pilots' => $pilots]);

        $result = app(PredictCrewBidLines::class)->forUser($package, $snapshot, 'FO', $user, ['F1', 'R2']);

        $this->assertSame('F1', $result['likely']);
        $this->assertSame('Renamed, Casey', $result['employee_name']);
        $this->assertSame('2026-07-15', $result['seniority_date']);
        $this->assertSame($before, $snapshot->fresh()->getRawOriginal());
    }

    public function test_missing_links_and_missing_roster_bidders_never_produce_personal_availability(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = $this->package();
        $snapshot = CrewBidSnapshot::factory()->create();
        $service = app(PredictCrewBidLines::class);
        $this->assertFalse($service->forUser($package, $snapshot, 'FO', $user, ['F1'])['available']);
        $list = PilotSeniorityList::factory()->create();
        $this->assertStringContainsString('Link your employee number', $service->forUser($package, $snapshot, 'FO', $user, ['F1'])['reason']);
        $user->forceFill(['crew_bid_employee_number' => '910003', 'crew_bid_employee_verified_at' => now()])->save();
        $pilots = $list->pilots;
        unset($pilots['910001']);
        $list->update(['pilots' => $pilots]);
        $result = $service->forUser($package, $snapshot, 'FO', $user, ['F1']);
        $this->assertFalse($result['available']);
        $this->assertNull($result['likely']);
        $this->assertStringContainsString('missing a shared bidder', $result['reason']);
        unset($pilots['910003']);
        $list->update(['pilots' => $pilots]);
        $this->assertStringContainsString('Your linked employee number is absent', $service->forUser($package, $snapshot, 'FO', $user, ['F1'])['reason']);
    }

    private function package(): BidPackage
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['captains'] = 1;
        $payload['lines']['F1']['first_officers'] = 2;
        $payload['lines']['R2']['captains'] = 1;
        $payload['lines']['R2']['first_officers'] = 1;
        $payload['lines']['F3'] = array_replace($payload['lines']['F1'], ['id' => 'F3', 'first_officers' => 1]);
        $package->update(['payload' => $payload]);

        return $package;
    }
}
