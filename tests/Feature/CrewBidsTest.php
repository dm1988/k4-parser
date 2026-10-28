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

    public function test_credit_and_block_sorts_are_independent_and_keep_manual_bid_order(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['credit'] = 5850;
        $payload['lines']['F1']['block'] = 5415;
        $payload['lines']['F3'] = array_replace($payload['lines']['F1'], ['id' => 'F3', 'credit' => 5700, 'block' => 5520]);
        $payload['lines']['F4'] = array_replace($payload['lines']['F1'], ['id' => 'F4', 'credit' => null, 'block' => null]);
        $payload['lines']['F5'] = array_replace($payload['lines']['F1'], ['id' => 'F5', 'credit_seconds' => 351030, 'block' => 5414]);
        $package->update(['payload' => $payload]);

        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertSee('Highest block time')->assertSee('97:30')->assertSee('97:30:30')->assertSee('90:15')
            ->call('add', 'F1')->call('add', 'F3')
            ->set('sort', 'credit')->assertHasNoErrors()
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F5', 'F1', 'F3', 'F4'])
            ->set('sort', 'block')->assertHasNoErrors()
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F3', 'F1', 'F5', 'F4'])
            ->assertSet('selections', ['F1', 'F3'])
            ->set('minimumCredit', 96)
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F1', 'F5']);
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

        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertViewHas('lines', fn (array $lines): bool => $lines[0]['carry_days'] === 3 && count($lines[0]['month']) === 30 && count($lines[0]['trip_details']) === 1 && $lines[0]['trip_details'][0]['minimum_rest'] === '17:30')
            ->assertSeeHtml('aria-label="3 carry-in days after month-end for F1"')
            ->assertSee('+3')->assertSee('Trip 749')->assertDontSee('Trip 999')
            ->assertSee('MIA–LUX–ANC–MIA')->assertSee('LUX, ANC')->assertSee('Minimum source rest')
            ->assertSeeHtml('aria-label="Leg timeline for trip 749, UTC"')
            ->assertSee('Leg timeline · UTC')->assertSee('Segment widths are illustrative.')
            ->assertSeeHtml('role="tablist"')->assertSee('Timeline / Legs')->assertSee('Layovers &amp; Rest', false)->assertSee('Pairing Metadata')
            ->assertSee('2026-11-30 07:00')->assertSee('2026-11-29 21:00')->assertSee('2026-12-03 13:30')
            ->assertSee('50h 0m')->assertSee('17h 30m')->assertSee('arrival to next departure')
            ->assertSee('8:00')->assertSee('100:00')->assertSee('Deadhead')->assertDontSee('Hotel details');

        $this->assertSame(1, substr_count($page->html(), 'K4 749'));
        $page->assertSeeHtml('aria-label="All linked trips start and end at MIA."')
            ->assertSeeHtml('aria-label="Includes deadhead legs in the available trip details."')
            ->assertSeeHtml('aria-label="Trip flag explanations for F1"');

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$page->html());
        $header = (new \DOMXPath($document))->query('//article[.//h3[contains(., "F1")]]/div[1]')->item(0);
        $this->assertStringContainsString('1 CA 2 FO', $header->textContent);
        $this->assertStringContainsString('DH', $header->textContent);
    }

    /** @param array<int, array{0: string, 1: string, 2: bool, 3?: bool}|null> $tripSpecifications
     * @param  list<string>  $expectedLabels
     */
    #[DataProvider('quickFlagSources')]
    public function test_quick_flags_require_complete_endpoints_but_keep_positive_deadhead_evidence(array $tripSpecifications, array $expectedLabels): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [];
        $payload['trips'] = [999 => [
            'id' => '999', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01',
            'start_city' => 'MIA', 'end_city' => 'CVG', 'report' => '00:00', 'release' => '03:30',
            'legs' => [['date' => '2026-11-01', 'origin' => 'MIA', 'destination' => 'CVG', 'departure' => '01:00', 'arrival' => '03:00', 'deadhead' => true]],
        ]];
        foreach ($tripSpecifications as $id => $specification) {
            $pairing = ['id' => (string) $id, 'date' => '2026-11-01'];
            $payload['lines']['F1']['pairings'][] = $pairing;
            $payload['lines']['F1']['pairings'][] = $pairing;
            if ($specification === null) {
                continue;
            }
            [$origin, $destination, $deadhead] = $specification;
            $via = $origin === 'ANC' ? 'CVG' : 'ANC';
            $payload['trips'][$id] = [
                'id' => (string) $id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-01',
                'start_city' => $origin, 'end_city' => $destination, 'report' => '00:00', 'release' => '06:30',
                'legs' => ($specification[3] ?? true) ? [
                    ['date' => '2026-11-01', 'origin' => $origin, 'destination' => $via, 'departure' => '01:00', 'arrival' => '03:00', 'deadhead' => false],
                    ['date' => '2026-11-01', 'origin' => $via, 'destination' => $destination ?: $origin, 'departure' => '04:00', 'arrival' => '06:00', 'deadhead' => $deadhead],
                ] : [],
            ];
        }
        $package->update(['payload' => $payload]);

        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines[0]['quick_flags'], 'label') === $expectedLabels);

        $this->assertSame($expectedLabels === [] ? 0 : 1, substr_count($page->html(), 'aria-label="Trip flags for F1"'));
        $this->assertSame($payload, $package->fresh()->payload);
    }

    /** @return array<string, array{array<int, array{0: string, 1: string, 2: bool, 3?: bool}|null>, list<string>}> */
    public static function quickFlagSources(): array
    {
        return [
            'return airport and deadhead' => [[749 => ['MIA', 'MIA', true]], ['MIA', 'DH']],
            'shared return airport across trips' => [[749 => ['MIA', 'MIA', false], 750 => ['MIA', 'MIA', false]], ['MIA']],
            'common open route' => [[749 => ['MIA', 'CVG', false]], ['MIA→CVG']],
            'different return airports' => [[749 => ['MIA', 'MIA', false], 750 => ['ANC', 'ANC', false]], ['Mixed ends']],
            'partial coverage with a known deadhead' => [[749 => ['MIA', 'MIA', true], 750 => null], ['DH']],
            'partial coverage without a known deadhead' => [[749 => ['MIA', 'MIA', false], 750 => null], []],
            'missing endpoint' => [[749 => ['MIA', '', false]], []],
            'known endpoints without legs' => [[749 => ['MIA', 'MIA', false, false]], ['MIA']],
            'no linked trips despite an unrelated deadhead' => [[], []],
        ];
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
            ->assertViewHas('lines', fn (array $lines): bool => $lines[0]['trip_details'][0]['layovers'] === [] && $lines[0]['trip_details'][0]['legs'][0]['connection_status'] === $status && $lines[0]['trip_details'][0]['legs'][0]['layover_highlight'] === null)
            ->assertSee($message)->assertSee('Connection uncertain')
            ->assertDontSee('Short layover · under 10h')->assertDontSee('Extended layover · 24h+');
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
            ->assertDontSee('Not recorded for this transport leg')->assertSee('3h 0m')->assertSee('0h 0m')->assertSee('3:50')
            ->assertSee('Ground transfer')->assertSee('Deadhead');
    }

    #[DataProvider('shortConnectionWindows')]
    public function test_layover_groups_highlight_only_connections_under_three_hours(string $departure, string $expectedGroup, string $duration): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [['id' => '749', 'date' => '2026-11-01']];
        $payload['trips']['749'] = [
            'id' => '749', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01',
            'start_city' => 'MIA', 'end_city' => 'NRT', 'report' => '06:00', 'release' => '23:00',
            'duty_days' => 1, 'crew_label' => '1 CA, 2 FO',
            'legs' => [
                ['date' => '2026-11-01', 'origin' => 'MIA', 'destination' => 'CVG', 'departure' => '07:00', 'arrival' => '10:00', 'deadhead' => false, 'rest_after' => '1:00'],
                ['date' => '2026-11-01', 'origin' => 'CVG', 'destination' => 'NRT', 'departure' => $departure, 'arrival' => '22:00', 'deadhead' => false],
            ],
        ];
        $package->update(['payload' => $payload]);

        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertViewHas('lines', function (array $lines) use ($expectedGroup, $duration): bool {
                $groups = $lines[0]['trip_details'][0]['layover_groups'];

                return count($groups[$expectedGroup]) === 1 && $groups[$expectedGroup][0]['duration'] === $duration
                    && $groups[$expectedGroup][0]['source_rest'] === '1:00'
                    && $groups[$expectedGroup === 'short' ? 'longer' : 'short'] === [];
            })
            ->assertSee($duration)->assertSee('1 CA, 2 FO')->assertSee('UTC interval');

        if ($expectedGroup === 'short') {
            $page->assertSee('Short connection · under 3h');
        } else {
            $page->assertDontSee('Short connection · under 3h');
        }
    }

    /** @return array<string, array{string, string, string}> */
    public static function shortConnectionWindows(): array
    {
        return [
            'one minute below threshold' => ['12:59', 'short', '2h 59m'],
            'exactly three hours' => ['13:00', 'longer', '3h 0m'],
        ];
    }

    #[DataProvider('layoverHighlightBoundaries')]
    public function test_layover_highlights_use_scheduled_duration_at_exact_thresholds(string $nextDate, string $departure, ?string $highlight): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [['id' => '749', 'date' => '2026-12-31']];
        $payload['trips']['749'] = [
            'id' => '749', 'start_date' => '2026-12-31', 'end_date' => $nextDate,
            'start_city' => 'MIA', 'end_city' => 'ICN', 'report' => '21:00', 'release' => '23:45',
            'legs' => [
                ['date' => '2026-12-31', 'origin' => 'MIA', 'destination' => 'NRT', 'departure' => '22:00', 'arrival' => '23:00', 'deadhead' => false, 'rest_after' => '8:00'],
                ['date' => $nextDate, 'origin' => 'NRT', 'destination' => 'ICN', 'departure' => $departure, 'arrival' => '23:30', 'deadhead' => false],
            ],
        ];
        $package->update(['payload' => $payload]);

        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertViewHas('lines', function (array $lines) use ($highlight): bool {
                $trip = $lines[0]['trip_details'][0];
                $layover = array_merge($trip['layover_groups']['short'], $trip['layover_groups']['longer'])[0];

                return $trip['legs'][0]['layover_highlight'] === $highlight && $layover['highlight'] === $highlight
                    && $trip['legs'][1]['layover_highlight'] === null && $trip['minimum_rest'] === '8:00';
            });

        if ($highlight === 'short') {
            $page->assertSee('Short layover · under 10h')->assertDontSee('Extended layover · 24h+');
        } elseif ($highlight === 'extended') {
            $page->assertSee('Extended layover · 24h+')->assertDontSee('Short layover · under 10h');
        } else {
            $page->assertDontSee('Short layover · under 10h')->assertDontSee('Extended layover · 24h+');
        }
    }

    /** @return array<string, array{string, string, string|null}> */
    public static function layoverHighlightBoundaries(): array
    {
        return [
            'zero gap' => ['2026-12-31', '23:00', 'short'],
            'one minute under ten hours' => ['2027-01-01', '08:59', 'short'],
            'exactly ten hours' => ['2027-01-01', '09:00', null],
            'one minute under twenty-four hours' => ['2027-01-01', '22:59', null],
            'exactly twenty-four hours' => ['2027-01-01', '23:00', 'extended'],
            'one minute over twenty-four hours' => ['2027-01-01', '23:01', 'extended'],
        ];
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
            ->assertDontSee('A leg timeline is unavailable because no dated legs were imported.');
    }

    public function test_missing_metrics_are_omitted_and_known_zero_values_remain_visible(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines'] = ['F1' => array_replace($payload['lines']['F1'], [
            'credit' => null, 'block' => null, 'duty_days' => null, 'start_city' => null, 'crew' => null,
            'pairings' => [['id' => '749', 'date' => '2026-11-01']],
        ])];
        $payload['lines']['F1']['calendar']['2026-11-01'] = ['status' => 'unknown', 'activity' => 'Unknown'];
        $payload['trips']['749'] = [
            'id' => '749', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01',
            'start_city' => 'YYZ', 'end_city' => 'YHM', 'report' => null, 'release' => '11:00',
            'legs' => [['date' => '2026-11-01', 'origin' => 'YYZ', 'destination' => 'YHM', 'departure' => '10:00', 'arrival' => '11:00', 'deadhead' => false, 'flight' => 'GT BUS']],
        ];
        $package->update(['payload' => $payload]);

        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->set('goals', ['credit', 'days_off', 'blocks'])
            ->call('add', 'F1')->set('showList', true)
            ->assertSee('Dates incomplete; conflicts unknown')->assertSee('1h 0m')->assertSee('Release · UTC')
            ->assertDontSee('Not available')->assertDontSee('Not recorded')->assertDontSee('Not present')
            ->assertDontSee('Min rest')->assertDontSee('Hotel details')->assertDontSee('Block · h:mm')
            ->assertDontSee('Report · UTC')->assertDontSee('Duty days (source)')->assertDontSee('Crew complement');

        $payload['lines']['F1'] = array_replace($payload['lines']['F1'], ['credit' => 0, 'block' => 0, 'duty_days' => 0]);
        foreach ($payload['lines']['F1']['calendar'] as &$entry) {
            $entry = ['status' => 'occupied', 'activity' => 'Trip 749'];
        }
        unset($entry);
        $payload['trips']['749']['report'] = '00:00';
        $payload['trips']['749']['duty_days'] = 0;
        $payload['trips']['749']['block_minutes'] = 0;
        $payload['trips']['749']['credit_minutes'] = 0;
        $payload['trips']['749']['legs'][0]['rest_after'] = '0:00';
        $package->update(['payload' => $payload]);

        $page->call('$refresh')->assertViewHas('lines', fn (array $lines): bool => $lines[0]['credit_label'] === '0:00' && $lines[0]['block_label'] === '0:00' && $lines[0]['days_off'] === 0)
            ->assertSee('Block · h:mm')->assertSee('Min rest')->assertSee('0:00')->assertSee('Duty days (source)')->assertSee('Report · UTC');
    }

    public function test_missing_trip_details_preserve_trip_identifiers_and_carry_count_without_placeholders(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [['id' => '749', 'date' => '2026-11-29']];
        $payload['lines']['F1']['carry_in'] = 2;
        $package->update(['payload' => $payload]);

        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertSee('+2')->assertSee('Trip 749')->assertDontSee('Trip details not available')
            ->assertDontSee('Trips file not imported or this trip was not found in the imported file.');

        $payload['lines']['F1']['carry_in'] = null;
        $package->update(['payload' => $payload]);
        Livewire::test(CrewBids::class)->assertViewHas('lines', fn (array $lines): bool => $lines[0]['carry_days'] === null)
            ->assertDontSee('+0')->assertDontSee('+2');
    }
}
