<?php

namespace Tests\Feature;

use App\DTOs\CrewBidBrowseCriteria;
use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\CrewBidPayProfile;
use App\Models\User;
use App\Services\CrewBids\BrowseBidLines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrewBidPayDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_and_package_are_sufficient_for_hard_and_reserve_pay_without_setup(): void
    {
        $this->package();
        $user = User::factory()->admin()->withCrewPaySettings('2026-01-01')->create();

        Livewire::actingAs($user)->test(CrewBids::class)->assertSee('$9,360.00')
            ->assertViewHas('allLines', fn (array $lines): bool => $lines['F1']['pay_estimate']->cents === 936000 && $lines['Q2']['pay_estimate']->cents === 760500)
            ->set('tab', 'reserve')->assertSee('$7,605.00')->call('openPayEditor', 'Q2')
            ->assertSet('payInput.category', 'reserve')->assertSet('payContext.qualified', true)
            ->assertSet('payContext.full_period', true)->assertSet('payContext.line_guarantee', true)
            ->assertSee('Advanced adjustments (optional)')->assertDontSee('Show Estimated pay in compact line metrics')
            ->assertDontSee('Applicable line category')->assertDontSee('Section 17.F.1');

        $this->assertDatabaseCount('crew_bid_pay_profiles', 0);
    }

    public function test_published_line_credit_is_the_automatic_minimum_despite_old_unchecked_setup_values(): void
    {
        $package = $this->package();
        $payload = $package->payload;
        $payload['lines']['F1']['source_credit'] = 5400;
        $package->update(['payload' => $payload]);
        $user = User::factory()->admin()->withCrewPaySettings('2026-01-01')->create();
        CrewBidPayProfile::factory()->create(['user_id' => $user->id, 'settings' => ['show_compact' => false],
            'scenarios' => [$package->id.':FO' => ['qualified' => false, 'full_period' => false, 'line_guarantee' => false,
                'lines' => ['F1' => ['mode' => 'source', 'category' => 'composite', 'reviewed' => false, 'rows' => []]]]]]);

        Livewire::actingAs($user)->test(CrewBids::class)->assertSee('$10,530.00')
            ->assertViewHas('allLines', fn (array $lines): bool => $lines['F1']['pay_estimate']->status === 'ready');
    }

    public function test_incomplete_pairing_data_shows_a_labelled_minimum_without_claiming_a_complete_total(): void
    {
        $package = $this->package();
        $payload = $package->payload;
        $payload['trips'] = [];
        $package->update(['payload' => $payload]);
        $user = User::factory()->admin()->withCrewPaySettings('2026-01-01')->create();

        Livewire::actingAs($user)->test(CrewBids::class)->assertSee('$7,605.00 minimum')
            ->assertViewHas('allLines', fn (array $lines): bool => $lines['F1']['pay_estimate']->cents === null && $lines['F1']['pay_estimate']->minimumCents === 760500);
    }

    public function test_full_breakdown_is_prepared_only_for_the_requested_line(): void
    {
        $this->package();
        $user = User::factory()->admin()->withCrewPaySettings('2026-01-01')->create();

        Livewire::actingAs($user)->test(CrewBids::class)
            ->assertViewHas('lineCards', fn (array $cards): bool => ! $cards[0]->payEstimate->loaded && $cards[0]->payEstimate->components === [])
            ->call('loadPayDetails', 'F1')->assertSet('payDetails', ['F1'])
            ->assertViewHas('lineCards', fn (array $cards): bool => $cards[0]->payEstimate->loaded && $cards[0]->payEstimate->components !== [])
            ->call('loadPayDetails', 'F1')->assertSet('payDetails', ['F1']);
    }

    public function test_advanced_exceptions_remain_explicit_and_do_not_change_the_normal_default(): void
    {
        $this->package();
        $user = User::factory()->admin()->withCrewPaySettings('2026-01-01')->create();

        Livewire::actingAs($user)->test(CrewBids::class)->call('openPayEditor')
            ->set('payContext.custom_assumptions', true)->set('payContext.full_period', false)->call('savePayInputs')
            ->assertHasNoErrors()->assertViewHas('allLines', fn (array $lines): bool => $lines['F1']['pay_estimate']->cents === null && $lines['F1']['pay_estimate']->minimumCents === null);
    }

    public function test_large_package_decrypts_payload_once_instead_of_once_per_line(): void
    {
        $source = $this->package();
        $payload = $source->payload;
        $template = $payload['lines']['F1'];
        $payload['lines'] = [];
        for ($index = 1; $index <= 300; $index++) {
            $payload['lines']['F'.$index] = array_replace($template, ['id' => 'F'.$index]);
        }
        $payload['source_padding'] = str_repeat('Representative source document data. ', 10000);
        $source->update(['payload' => $payload]);
        $package = new class extends BidPackage
        {
            public int $payloadReads = 0;

            public function getAttribute(mixed $key): mixed
            {
                if ($key === 'payload') {
                    $this->payloadReads++;
                }

                return parent::getAttribute($key);
            }
        };
        $package->setRawAttributes($source->getAttributes(), true);
        $criteria = new CrewBidBrowseCriteria(seat: 'FO', preferences: ['goals' => [], 'days_off' => [], 'start_city' => '', 'initialized' => true]);
        $start = hrtime(true);

        $result = app(BrowseBidLines::class)->browse($package, $criteria, ['settings' => ['longevity_date' => '2026-01-01']]);

        $this->assertSame(1, $package->payloadReads);
        $this->assertCount(300, $result->lines);
        $this->assertSame(936000, $result->lines[0]['pay_estimate']->cents);
        if (getenv('CREW_PAY_BENCHMARK')) {
            fwrite(STDERR, sprintf("\n300-line pay browsing: %.1f ms; payload decryptions: %d\n", (hrtime(true) - $start) / 1000000, $package->payloadReads));
        }
    }

    private function package(): BidPackage
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['source_credit'] = 3900;
        $payload['lines']['F1']['pairings'] = [['id' => 'T1']];
        $payload['lines']['Q2'] = array_replace($payload['lines']['F1'], ['id' => 'Q2', 'type' => 'reserve', 'pairings' => []]);
        unset($payload['lines']['R2']);
        $payload['trips'] = ['T1' => ['id' => 'T1', 'start_date' => '2026-11-01', 'end_date' => '2026-11-15',
            'start_city' => 'ANC', 'end_city' => 'ANC', 'report' => '09:00', 'release' => '18:00',
            'credit_minutes' => 4800, 'block_minutes' => 4200, 'legs' => []]];
        $package->update(['payload' => $payload]);

        return $package;
    }
}
