<?php

namespace Tests\Feature;

use App\Actions\CrewBids\SaveCrewBidPayProfile;
use App\DTOs\CrewBidBrowseCriteria;
use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\CrewBidPayProfile;
use App\Models\User;
use App\View\Models\CrewBidsPageDataFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CrewBidPayTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_correct_remove_and_render_private_pay_without_changing_bids(): void
    {
        $package = BidPackage::factory()->create();
        $user = User::factory()->admin()->withCrewPaySettings('2026-01-01')->create(['crew_bid_seat_preference' => 'FO']);
        $page = Livewire::actingAs($user)->test(CrewBids::class)->call('finishSetup')->call('add', 'F1')
            ->set('minimumDaysOff', 14)->call('openPayEditor', 'F1');
        $workspace = BidWorkspace::query()->where('user_id', $user->id)->sole()->attributesToArray();
        $source = $package->payload;

        $page->set('paySettings', $this->settings())->set('payContext', $this->context())->set('payInput', $this->input('80:00'))
            ->call('savePayInputs')->assertHasNoErrors()->assertSet('showPayEditor', false)
            ->assertSet('minimumDaysOff', 14)->assertSet('selections', ['F1'])
            ->assertSee('$9,360.00')->assertSee('Highest estimated line value')
            ->assertViewHas('allLines', fn (array $lines): bool => $lines['F1']['pay_estimate']->cents === 936000);

        $profile = CrewBidPayProfile::query()->where('user_id', $user->id)->sole();
        $raw = DB::table('crew_bid_pay_profiles')->where('id', $profile->id)->first();
        $this->assertStringNotContainsString('show_compact', $raw->settings);
        $this->assertSame($this->settings(), json_decode(Crypt::decryptString($raw->settings), true));
        $this->assertArrayNotHasKey('settings', $profile->toArray());
        $this->assertArrayNotHasKey('scenarios', $profile->toArray());
        $this->assertSame($workspace, BidWorkspace::query()->where('user_id', $user->id)->sole()->attributesToArray());
        $this->assertSame($source, $package->fresh()->payload);

        $page->call('openPayEditor', 'F1')->set('payInput.rows.0.duration', '90:00')->call('savePayInputs')
            ->assertHasNoErrors()->assertSee('$10,530.00')
            ->call('openPayEditor')->call('forgetPayInputs')->assertHasNoErrors()
            ->assertSet('selections', ['F1'])->assertDontSee('$10,530.00');
        $this->assertSame([], $profile->fresh()->settings);
        $this->assertSame([], $profile->fresh()->scenarios);
        $this->assertSame(['longevity_date' => '2026-01-01', 'check_airman' => false], $user->fresh()->crew_pay_settings);
    }

    public function test_other_viewers_and_other_package_seats_cannot_receive_private_estimates(): void
    {
        $package = BidPackage::factory()->create();
        $owner = User::factory()->admin()->withCrewPaySettings('2026-01-01')->create();
        $other = User::factory()->admin()->create();
        $this->save($owner, $package);

        Livewire::actingAs($other)->test(CrewBids::class)->assertDontSee('$9,360.00')->call('openPayEditor', 'F1')
            ->assertSet('paySettings', ['show_compact' => true])->assertSet('payInput.rows', [])->assertDontSee('2026-01-01');
        $this->assertDatabaseCount('crew_bid_pay_profiles', 1);
        $criteria = new CrewBidBrowseCriteria(seat: 'CA', preferences: ['goals' => [], 'days_off' => [], 'start_city' => '']);
        $seat = app(CrewBidsPageDataFactory::class)->make($package, $owner, $criteria);
        $revision = BidPackage::factory()->create();
        $next = app(CrewBidsPageDataFactory::class)->make($revision, $owner, new CrewBidBrowseCriteria(seat: 'FO', preferences: $criteria->preferences));

        $this->assertNull($seat->browse->allLines['F1']['pay_estimate']->cents);
        $this->assertNull($next->browse->allLines['F1']['pay_estimate']->cents);
    }

    public function test_pay_sort_uses_ready_rounded_values_natural_ties_and_preserves_manual_ranks_and_bulk_order(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        foreach (['F2', 'F10', 'F3'] as $id) {
            $payload['lines'][$id] = array_replace($payload['lines']['F1'], ['id' => $id]);
        }
        $package->update(['payload' => $payload]);
        $user = User::factory()->admin()->withCrewPaySettings('2026-01-01')->create();
        $profile = $this->save($user, $package);
        foreach (['F10' => '90:00', 'F2' => '90:00'] as $id => $duration) {
            $profile = app(SaveCrewBidPayProfile::class)->handle($user, $package, 'FO', $profile->revision, $this->settings(), $this->context(), $id, $this->input($duration));
        }

        $page = Livewire::actingAs($user)->test(CrewBids::class)->call('add', 'F1')->set('sort', 'pay')
            ->assertHasNoErrors()->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F2', 'F10', 'F1', 'F3'])
            ->assertSet('selections', ['F1'])->call('addMatching')->assertSet('selections', ['F1', 'F2', 'F10', 'F3']);

        $page->set('showList', true)->set('showReview', true)->assertSee("1. F1\n2. F2\n3. F10\n4. F3");
    }

    public function test_stale_edit_retains_draft_and_does_not_overwrite_saved_context(): void
    {
        $package = BidPackage::factory()->create();
        $user = User::factory()->admin()->withCrewPaySettings('2026-01-01')->create();
        $first = Livewire::actingAs($user)->test(CrewBids::class)->call('openPayEditor', 'F1');
        $this->save($user, $package);

        $first->set('paySettings', $this->settings())->set('payContext', $this->context())->set('payInput', $this->input('100:00'))
            ->call('savePayInputs')->assertHasErrors('pay')->assertSet('showPayEditor', true)->assertSet('payInput.rows.0.duration', '100:00');

        $this->assertSame('80:00', CrewBidPayProfile::query()->sole()->scenarios[$package->id.':FO']['lines']['F1']['rows'][0]['duration']);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_do_not_create_a_profile(string $path, mixed $value): void
    {
        BidPackage::factory()->create();
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)->call('openPayEditor', 'F1')
            ->set('paySettings', $this->settings())->set('payContext', $this->context())->set('payInput', $this->input());

        $page->set($path, $value)->call('savePayInputs')->assertHasErrors();

        $this->assertDatabaseCount('crew_bid_pay_profiles', 0);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidInputs(): array
    {
        return [
            'legacy longevity is no longer writable here' => ['paySettings.longevity_date', '2026-01-01'],
            'profile override is no longer writable here' => ['payContext.check_airman', true],
            'unknown setting' => ['paySettings.user_id', 999],
            'invalid time' => ['payInput.rows.0.duration', '1:99'],
            'unknown category' => ['payInput.rows.0.type', 'hazard'],
            'ordinary in source mode' => ['payInput.mode', 'source'],
            'invalid line category' => ['payInput.category', 'invented'],
            'out of period' => ['payInput.rows.0.date', '2026-12-01'],
        ];
    }

    public function test_open_time_requires_private_mode_and_repeated_assignment_cannot_be_paid_twice(): void
    {
        $package = BidPackage::factory()->create();
        $user = User::factory()->admin()->create();
        $page = Livewire::actingAs($user)->test(CrewBids::class)->call('openPayEditor', 'F1')
            ->set('paySettings', $this->settings())->set('payContext', $this->context())->set('payInput', $this->input())
            ->set('payInput.rows.0.type', 'open_time')->set('payInput.mode', 'source');

        $page->call('savePayInputs')->assertHasErrors('pay');
        $input = $this->input();
        $input['rows'][] = $input['rows'][0];
        $input['rows'][1]['type'] = 'open_time';
        $page->set('payInput', $input)->call('savePayInputs')->assertHasErrors('pay');

        $this->assertDatabaseCount('crew_bid_pay_profiles', 0);
        $this->assertSame([], $package->payload['trips']);
    }

    public function test_distinct_additional_pay_rules_may_share_an_ordinary_assignment_reference(): void
    {
        BidPackage::factory()->create();
        $input = $this->input('80:00');
        $input['rows'][] = array_replace($input['rows'][0], ['type' => 'maintenance', 'duration' => '1:00']);

        Livewire::actingAs(User::factory()->admin()->withCrewPaySettings('2026-01-01')->create())->test(CrewBids::class)->call('openPayEditor', 'F1')
            ->set('paySettings', $this->settings())->set('payContext', $this->context())->set('payInput', $input)
            ->call('savePayInputs')->assertHasNoErrors()->assertSee('$9,385.00');

        $this->assertDatabaseCount('crew_bid_pay_profiles', 1);
    }

    public function test_ineligible_lines_and_archived_packages_are_not_editable_by_new_viewers(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['first_officers'] = 0;
        $package->update(['payload' => $payload]);
        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)->test(CrewBids::class)->call('openPayEditor', 'F1')->assertNotFound();
        $package->update(['archived_at' => now()]);
        $this->expectException(HttpException::class);
        app(SaveCrewBidPayProfile::class)->handle($user, $package, 'FO', 0, $this->settings(), $this->context(), null, []);
    }

    public function test_non_entitled_users_cannot_write_pay_profiles(): void
    {
        $user = User::factory()->create();
        $package = BidPackage::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(SaveCrewBidPayProfile::class)->handle($user, $package, 'FO', 0, $this->settings(), $this->context(), 'F1', $this->input());
    }

    public function test_profile_changes_recalculate_pay_and_clearing_longevity_disables_totals(): void
    {
        $package = BidPackage::factory()->create();
        $user = User::factory()->admin()->withCrewPaySettings('2026-01-01')->create();
        $this->save($user, $package);
        Livewire::actingAs($user)->test(CrewBids::class)->assertSee('$9,360.00')->call('openPayEditor', 'F1')
            ->assertSee('Edit pay settings in your profile')->assertDontSee('wire:model="paySettings.longevity_date"', false)
            ->assertDontSee('wire:model="payContext.check_airman"', false);

        $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email,
            'crew_pay_settings' => ['longevity_date' => '2026-01-01', 'check_airman' => '1']])->assertSessionHasNoErrors();

        Livewire::actingAs($user->refresh())->test(CrewBids::class)->assertSee('$10,560.00');
        $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email,
            'crew_pay_settings' => ['longevity_date' => null, 'check_airman' => '0']])->assertSessionHasNoErrors();
        Livewire::actingAs($user->refresh())->test(CrewBids::class)
            ->assertViewHas('allLines', fn (array $lines): bool => $lines['F1']['pay_estimate']->cents === null)
            ->assertDontSee('$10,560.00');
    }

    public function test_conflicting_migrated_override_requires_a_profile_choice_and_ignores_legacy_values(): void
    {
        $package = BidPackage::factory()->create();
        $user = User::factory()->admin()->withCrewPaySettings('2026-01-01')->create();
        $profile = $this->save($user, $package);
        $user->update(['crew_pay_settings' => ['longevity_date' => '2026-01-01', 'check_airman' => null]]);
        $scenarios = $profile->scenarios;
        $scenarios[$package->id.':FO']['check_airman'] = true;
        $profile->update(['settings' => ['longevity_date' => '2000-01-01', 'show_compact' => true], 'scenarios' => $scenarios]);

        Livewire::actingAs($user)->test(CrewBids::class)->call('loadPayDetails', 'F1')->assertSee('Saved Check Airman scenarios disagree')
            ->assertViewHas('allLines', fn (array $lines): bool => $lines['F1']['pay_estimate']->cents === null);
        $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email,
            'crew_pay_settings' => ['longevity_date' => '2026-01-01', 'check_airman' => '0']])->assertSessionHasNoErrors();

        Livewire::actingAs($user->refresh())->test(CrewBids::class)->assertSee('$9,360.00');
    }

    private function save(User $user, BidPackage $package): CrewBidPayProfile
    {
        return app(SaveCrewBidPayProfile::class)->handle($user, $package, 'FO', 0, $this->settings(), $this->context(), 'F1', $this->input('80:00'));
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return ['show_compact' => true];
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        return ['qualified' => true, 'full_period' => true, 'line_guarantee' => false, 'unsupported' => false, 'custom_assumptions' => true];
    }

    /** @return array<string, mixed> */
    private function input(string $duration = '0:00'): array
    {
        return ['category' => 'hard', 'mode' => 'manual', 'reviewed' => true,
            'rows' => [['assignment' => 'Ordinary award', 'type' => 'credit', 'date' => '2026-11-15', 'duration' => $duration, 'actual' => '']]];
    }
}
