<?php

namespace Tests\Feature;

use App\Actions\CrewBids\SaveBidWorkspace;
use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrewBidsTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_policy_hides_navigation_and_protects_routes(): void
    {
        $this->get(route('crew-bids.index'))->assertRedirect(route('login'));
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('crew-bids.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee('href="'.route('crew-bids.index').'"', false);
        config(['features.crew_bids.for_all_users' => true]);
        $this->get(route('crew-bids.index'))->assertOk()->assertSee('No bid package available yet')->assertDontSee('Manage package');
        $this->assertFalse(Gate::forUser($user)->allows('manage-crew-bids'));
        $this->assertTrue(Gate::forUser($user)->allows('use-crew-bids'));
        config(['features.crew_bids.enabled' => false]);
        $this->get(route('crew-bids.index'))->assertNotFound();
        $this->assertFalse(Gate::forUser($user)->allows('use-crew-bids'));
    }

    public function test_unverified_users_cannot_open_the_workspace(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get(route('crew-bids.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_admin_navigation_places_bids_after_flight_plan_brief(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('crew-bids.index'))->assertOk()->assertSeeInOrder(['Flight Plan Brief', 'K4 Bids', 'Admin Panel']);
    }

    public function test_flying_and_reserve_choices_share_manual_order_and_survive_reload(): void
    {
        $package = BidPackage::factory()->create();
        $user = User::factory()->admin()->create();
        Livewire::actingAs($user)->test(CrewBids::class)->call('finishSetup')
            ->call('add', 'F1')->call('add', 'R2')->call('add', 'F1')->assertSet('selections', ['F1', 'R2'])
            ->call('move', 'R2', 0)->set('sort', 'credit')->assertSet('selections', ['R2', 'F1'])
            ->set('showList', true)->set('showReview', true)->assertSee("1. R2\n2. F1")
            ->assertSet('dirty', false);
        $saved = BidWorkspace::query()->where('user_id', $user->id)->sole();
        $this->assertSame($package->id, $saved->bid_package_id);
        $this->assertSame(['R2', 'F1'], $saved->selections);
        $raw = DB::table('bid_workspaces')->where('id', $saved->id)->value('selections');
        $this->assertSame('["R2","F1"]', Crypt::decryptString($raw));
        Livewire::test(CrewBids::class)->assertSet('selections', ['R2', 'F1'])->assertSet('showPreferences', false);
    }

    public function test_date_conflicts_exclude_available_lines_but_retain_selected_choices(): void
    {
        BidPackage::factory()->create();
        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->call('add', 'F1')->call('toggleDate', '2026-11-03')
            ->assertSet('selections', ['F1'])->assertViewHas('lines', fn (array $lines): bool => count($lines) === 0)
            ->set('showList', true)->assertSee('Conflicts with requested UTC days off: 2026-11-03')
            ->call('add', 'R2')->assertHasErrors('workspace')->assertSet('selections', ['F1']);
    }

    public function test_unknown_dates_remain_visible_with_a_warning_and_cannot_claim_days_off(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['calendar']['2026-11-20'] = ['status' => 'unknown', 'activity' => 'Unknown'];
        $package->update(['payload' => $payload]);
        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)->call('toggleDate', '2026-11-20')
            ->assertSee('Dates incomplete; conflicts unknown')->assertViewHas('lines', fn (array $lines): bool => count($lines) === 1 && $lines[0]['days_off'] === null)
            ->call('add', 'F1')->assertSet('selections', ['F1']);
    }

    public function test_priorities_are_strict_and_start_city_is_a_hard_filter(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F3'] = array_replace($payload['lines']['F1'], ['id' => 'F3', 'credit' => 9999, 'start_city' => 'LAX']);
        $payload['lines']['F3']['calendar']['2026-11-16'] = ['status' => 'occupied', 'activity' => 'Trip / occupied'];
        $package->update(['payload' => $payload]);
        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->set('goals', ['days_off', 'credit'])->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F1', 'F3'])
            ->assertSee('Why this matched')->assertSee('Priorities in order; each lower goal breaks ties only.')
            ->assertSeeInOrder(['F1', 'Credit', 'Days off', 'Why this matched'])
            ->assertSeeInOrder(['Maximize days off: 15', 'Maximize credit: 65:00'])
            ->call('sortGoal', 'credit', 0)->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F3', 'F1'])
            ->set('startCity', 'ANC')->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F1'])
            ->assertSee('Required start city ANC: Matches');
    }

    public function test_private_lists_are_isolated_and_zero_seat_lines_cannot_be_selected(): void
    {
        $package = BidPackage::factory()->create();
        $other = User::factory()->create();
        BidWorkspace::factory()->create(['user_id' => $other->id, 'bid_package_id' => $package->id, 'selections' => ['R2']]);
        $payload = $package->payload;
        $payload['lines']['F1']['captains'] = 0;
        $package->update(['payload' => $payload]);
        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)->assertSet('selections', [])
            ->set('contextSeat', 'CA')->call('changeContext')->call('add', 'F1')->assertNotFound();
        $this->assertSame(['R2'], BidWorkspace::query()->where('user_id', $other->id)->sole()->selections);
    }

    public function test_conflicting_tabs_retain_local_edits_and_require_saved_reload(): void
    {
        BidPackage::factory()->create();
        $user = User::factory()->admin()->create();
        $first = Livewire::actingAs($user)->test(CrewBids::class);
        $second = Livewire::test(CrewBids::class);
        $first->call('add', 'F1');
        $second->call('add', 'R2')->assertHasErrors('workspace')->assertSet('dirty', true)->assertSet('selections', ['R2']);
        $this->assertSame(['F1'], BidWorkspace::query()->where('user_id', $user->id)->sole()->selections);
        $second->call('reloadSaved')->assertSet('dirty', false)->assertSet('selections', ['F1']);
    }

    public function test_new_revision_requires_confirmation_and_retains_prior_list(): void
    {
        $prior = BidPackage::factory()->create();
        $user = User::factory()->admin()->create();
        $page = Livewire::actingAs($user)->test(CrewBids::class)->call('add', 'F1')->call('add', 'R2');
        $payload = $prior->payload;
        unset($payload['lines']['R2']);
        $payload['lines']['F1']['credit'] = 4000;
        $next = BidPackage::factory()->create(['payload' => $payload, 'source_revision' => 'LINES_5']);
        $page->call('reviewRevision', $next->id)->assertSet('packageId', $prior->id)->assertSee('Unavailable — will be omitted')->assertSee('Credit 65:00 → 66:40')
            ->call('confirmRevision')->assertSet('packageId', $next->id)->assertSet('selections', ['F1']);
        $this->assertSame(['F1', 'R2'], BidWorkspace::query()->where('user_id', $user->id)->where('bid_package_id', $prior->id)->sole()->selections);
    }

    public function test_actions_recheck_access_after_feature_is_revoked(): void
    {
        BidPackage::factory()->create();
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class);
        config(['features.crew_bids.enabled' => false]);
        $page->call('add', 'F1')->assertNotFound();
        $this->assertDatabaseCount('bid_workspaces', 0);
    }

    public function test_non_admin_cannot_import_even_when_general_access_is_enabled(): void
    {
        config(['features.crew_bids.for_all_users' => true]);
        Livewire::actingAs(User::factory()->create())->test(CrewBids::class)->call('previewImport')->assertForbidden();
    }

    public function test_invalid_dates_and_duplicate_goals_do_not_persist(): void
    {
        BidPackage::factory()->create();
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class);
        $page->call('toggleDate', '2026-12-01')->assertHasErrors('daysOff');
        $page->set('goals', ['credit', 'credit'])->assertHasErrors('preferences.goals.0');
        $this->assertDatabaseCount('bid_workspaces', 0);
    }

    public function test_source_metadata_is_escaped_and_raw_payload_is_not_in_livewire_state(): void
    {
        BidPackage::factory()->create(['source_revision' => '<script>unsafe()</script>']);
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false);
        $this->assertArrayNotHasKey('payload', $page->snapshot['data']);
        $this->assertArrayNotHasKey('allLines', $page->snapshot['data']);
    }

    public function test_owner_account_deletion_removes_private_workspace(): void
    {
        $workspace = BidWorkspace::factory()->create();
        User::query()->findOrFail($workspace->user_id)->delete();
        $this->assertModelMissing($workspace);
    }

    public function test_failed_save_retains_choices_and_retry_persists_them(): void
    {
        BidPackage::factory()->create();
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class);
        Exceptions::fake();
        $this->mock(SaveBidWorkspace::class)->shouldReceive('handle')->once()->andThrow(new \RuntimeException('Simulated database outage'));
        $page->call('add', 'F1')->assertSet('dirty', true)->assertSet('selections', ['F1'])->assertSee('Save failed. Your edits are retained.');
        Exceptions::assertReported(\RuntimeException::class);
        $this->app->forgetInstance(SaveBidWorkspace::class);
        $page->call('retrySave')->assertSet('dirty', false);
        $this->assertSame(['F1'], BidWorkspace::query()->sole()->selections);
    }

    public function test_date_range_undo_and_unknown_activity_leave_manual_choices_consistent(): void
    {
        BidPackage::factory()->create();
        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->call('add', 'F1')->call('add', 'R2')->call('undoAdd')->assertSet('selections', ['F1'])
            ->set('rangeStart', '2026-11-20')->set('rangeEnd', '2026-11-22')->call('addRange')
            ->assertSet('daysOff', ['2026-11-20', '2026-11-21', '2026-11-22'])->assertSet('selections', ['F1']);
    }

    public function test_fewer_blocks_goal_counts_contiguous_occupied_dates_including_rest(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F3'] = array_replace($payload['lines']['F1'], ['id' => 'F3']);
        $payload['lines']['F3']['calendar']['2026-11-07'] = ['status' => 'off', 'activity' => 'Off'];
        $payload['lines']['F1']['calendar']['2026-11-07']['activity'] = 'Layover / rest';
        $package->update(['payload' => $payload]);
        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)->set('goals', ['blocks'])
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F1', 'F3'] && $lines[0]['blocks'] === 1 && $lines[1]['blocks'] === 2);
    }

    public function test_line_cards_show_carry_days_and_inline_trip_previews_with_source_rest(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [['id' => '749', 'date' => '2026-11-29'], ['id' => '749', 'date' => '2026-11-30']];
        $payload['trips']['749'] = [
            'id' => '749', 'start_date' => '2026-11-29', 'end_date' => '2026-12-03',
            'start_city' => 'MIA', 'end_city' => 'MIA', 'report' => '21:00', 'release' => '13:30',
            'legs' => [
                ['date' => '2026-11-29', 'origin' => 'MIA', 'destination' => 'LUX', 'departure' => '23:00', 'arrival' => '07:00', 'deadhead' => false, 'flight' => 'K4 749', 'block' => '8:00', 'rest_after' => '100:00'],
                ['date' => '2026-12-02', 'origin' => 'LUX', 'destination' => 'ANC', 'departure' => '09:00', 'arrival' => '18:00', 'deadhead' => false, 'block' => '9:00', 'rest_after' => '17:30'],
                ['date' => '2026-12-03', 'origin' => 'ANC', 'destination' => 'MIA', 'departure' => '11:30', 'arrival' => '13:00', 'deadhead' => true],
            ],
        ];
        $payload['trips']['999'] = array_replace($payload['trips']['749'], ['id' => '999']);
        $package->update(['payload' => $payload]);

        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertViewHas('lines', fn (array $lines): bool => $lines[0]['carry_days'] === 3 && count($lines[0]['month']) === 30 && count($lines[0]['trip_details']) === 1 && $lines[0]['trip_details'][0]['minimum_rest'] === '17:30')
            ->assertSeeHtml('aria-label="3 carry-in days after month-end for F1"')
            ->assertSee('+3')->assertSee('Trip 749')->assertDontSee('Trip 999')
            ->assertSee('MIA–LUX–ANC–MIA')->assertSee('LUX, ANC')->assertSee('Minimum source rest')
            ->assertSeeHtml('aria-label="Leg timeline for trip 749, UTC"')
            ->assertSee('Leg timeline · UTC')->assertSee('Segment widths are illustrative.')
            ->assertSee('2026-11-30 07:00')->assertSee('2026-11-29 21:00')->assertSee('2026-12-03 13:30')
            ->assertSee('50h 0m')->assertSee('17h 30m')->assertSee('arrival to next departure')
            ->assertSee('8:00')->assertSee('100:00')->assertSee('Deadhead')->assertSee('Hotel details: Not present');
    }

    public function test_legacy_trip_data_calculates_block_and_overnight_layovers_across_the_year_boundary(): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [['id' => '749', 'date' => '2026-12-31']];
        $payload['trips']['749'] = [
            'id' => '749', 'start_date' => '2026-12-31', 'end_date' => '2027-01-02',
            'start_city' => 'MIA', 'end_city' => 'NRT', 'report' => '22:30', 'release' => '17:35',
            'legs' => [
                ['date' => '2026-12-31', 'origin' => 'MIA', 'destination' => 'CVG', 'departure' => '23:30', 'arrival' => '01:00', 'deadhead' => false],
                ['date' => '2027-01-02', 'origin' => 'CVG', 'destination' => 'NRT', 'departure' => '05:15', 'arrival' => '17:05', 'deadhead' => false],
            ],
        ];
        $package->update(['payload' => $payload]);

        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertViewHas('lines', function (array $lines): bool {
                $trip = $lines[0]['trip_details'][0];

                return $trip['legs'][0]['block'] === '1:30' && $trip['legs'][1]['block'] === '11:50'
                    && $trip['legs'][0]['block_basis'] === 'calculated' && $trip['minimum_rest'] === null
                    && $trip['layovers'] === [['airport' => 'CVG', 'duration' => '28h 15m']];
            })
            ->assertSee('11:50')->assertSee('28h 15m')->assertSee('2027-01-01 01:00')
            ->assertSee('Arrives 2027-01-01')
            ->assertSee('calculated from scheduled UTC times')->assertSee('Source rest is shown separately.')
            ->assertDontSee('Block (h:mm): Not present');

        $this->assertSame($payload, $package->fresh()->payload);
    }

    #[DataProvider('unavailableLayovers')]
    public function test_inconsistent_connections_do_not_claim_a_layover(string $nextOrigin, string $nextDeparture, string $status, string $message): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [['id' => '749', 'date' => '2026-11-01']];
        $payload['trips']['749'] = [
            'id' => '749', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01',
            'start_city' => 'MIA', 'end_city' => 'NRT', 'report' => '06:00', 'release' => '23:00',
            'legs' => [
                ['date' => '2026-11-01', 'origin' => 'MIA', 'destination' => 'CVG', 'departure' => '07:00', 'arrival' => '10:00', 'deadhead' => false],
                ['date' => '2026-11-01', 'origin' => $nextOrigin, 'destination' => 'NRT', 'departure' => $nextDeparture, 'arrival' => '22:00', 'deadhead' => false],
            ],
        ];
        $package->update(['payload' => $payload]);

        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertViewHas('lines', fn (array $lines): bool => $lines[0]['trip_details'][0]['layovers'] === [] && $lines[0]['trip_details'][0]['legs'][0]['connection_status'] === $status)
            ->assertSee($message)->assertSee('Connection uncertain');
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function unavailableLayovers(): array
    {
        return [
            'overlapping UTC times' => ['CVG', '09:59', 'overlap', 'Layover unavailable: consecutive leg times overlap.'],
            'airport transfer' => ['ORD', '11:00', 'airport_change', 'Layover unavailable: next leg departs from a different airport.'],
        ];
    }

    public function test_transport_elapsed_time_is_separate_from_block_and_zero_length_connections_are_preserved(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [['id' => '749', 'date' => '2026-11-01']];
        $payload['trips']['749'] = [
            'id' => '749', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01',
            'start_city' => 'MIA', 'end_city' => 'CVG', 'report' => '06:00', 'release' => '17:00',
            'legs' => [
                ['date' => '2026-11-01', 'origin' => 'MIA', 'destination' => 'YYZ', 'departure' => '07:00', 'arrival' => '10:00', 'deadhead' => true],
                ['date' => '2026-11-01', 'origin' => 'YYZ', 'destination' => 'YHM', 'departure' => '10:00', 'arrival' => '11:00', 'deadhead' => false, 'flight' => 'GT J1 BUS G'],
                ['date' => '2026-11-01', 'origin' => 'YHM', 'destination' => 'CVG', 'departure' => '12:00', 'arrival' => '16:00', 'deadhead' => false, 'block' => '3:50'],
            ],
        ];
        $package->update(['payload' => $payload]);

        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertViewHas('lines', function (array $lines): bool {
                $legs = $lines[0]['trip_details'][0]['legs'];

                return $legs[0]['block_basis'] === 'unavailable' && $legs[1]['block_basis'] === 'unavailable'
                    && $legs[0]['layover_label'] === '0h 0m' && $legs[2]['block'] === '3:50'
                    && array_column($legs, 'timeline_type') === ['deadhead', 'ground', 'flying']
                    && $legs[2]['block_basis'] === 'source' && $legs[2]['connection_status'] === null;
            })
            ->assertSee('Not recorded for this transport leg')->assertSee('3h 0m')->assertSee('0h 0m')->assertSee('3:50')
            ->assertSee('Ground transfer')->assertSee('Deadhead');
    }

    public function test_carry_days_count_unique_next_year_dates_without_changing_monthly_days_off(): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $payload = $package->payload;
        $calendar = [];
        for ($day = 1; $day <= 31; $day++) {
            $calendar[sprintf('2026-12-%02d', $day)] = ['status' => 'off', 'activity' => 'Off'];
        }
        $payload['lines']['F1']['calendar'] = $calendar;
        $payload['lines']['F1']['pairings'] = [['id' => '749', 'date' => '2026-12-30'], ['id' => '750', 'date' => '2026-12-31']];
        foreach (['749' => '2027-01-02', '750' => '2027-01-03'] as $id => $end) {
            $payload['trips'][$id] = ['id' => (string) $id, 'start_date' => '2026-12-30', 'end_date' => $end, 'start_city' => 'MIA', 'end_city' => 'LUX', 'report' => null, 'release' => '12:00', 'legs' => []];
        }
        $package->update(['payload' => $payload]);

        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertViewHas('lines', fn (array $lines): bool => $lines[0]['carry_days'] === 3 && $lines[0]['days_off'] === 31)
            ->assertSee('A leg timeline is unavailable because no dated legs were imported.');
    }

    public function test_missing_trip_details_preserve_source_carry_count_and_explicit_missing_data(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [['id' => '749', 'date' => '2026-11-29']];
        $payload['lines']['F1']['carry_in'] = 2;
        $package->update(['payload' => $payload]);

        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertSee('+2')->assertSee('Trip 749')->assertSee('Trip details not available')
            ->assertSee('Trips file not imported or this trip was not found in the imported file.');

        $payload['lines']['F1']['carry_in'] = null;
        $package->update(['payload' => $payload]);
        Livewire::test(CrewBids::class)->assertViewHas('lines', fn (array $lines): bool => $lines[0]['carry_days'] === null)
            ->assertDontSee('+0')->assertDontSee('+2');
    }
}
