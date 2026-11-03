<?php

namespace Tests\Feature;

use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrewBidAdvancedFiltersTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('filterGroupResets')]
    public function test_clearing_one_group_preserves_other_filters_preferences_and_bid_order(string $action, ?int $credit, ?int $daysOff, string $city, bool $layovers, int $activeCount): void
    {
        BidPackage::factory()->create();
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->call('add', 'R2')->call('add', 'F1')
            ->set('goals', ['credit'])->set('startCity', 'ANC')->set('daysOff', ['2026-11-20'])
            ->set('sort', 'block')->set('minimumCredit', 100)->set('minimumDaysOff', 20)
            ->set('layoverCity', 'NRT')->set('extendedLayoversOnly', true)->set('avoidShortLayovers', true)
            ->set('selectedOnly', true)->assertViewHas('activeFilterCount', 6);
        $workspace = BidWorkspace::query()->sole()->toArray();

        $page->call($action)->assertHasNoErrors()
            ->assertSet('minimumCredit', $credit)->assertSet('minimumDaysOff', $daysOff)
            ->assertSet('layoverCity', $city)->assertSet('extendedLayoversOnly', $layovers)->assertSet('avoidShortLayovers', $layovers)
            ->assertSet('selectedOnly', true)->assertSet('sort', 'block')
            ->assertSet('goals', ['credit'])->assertSet('startCity', 'ANC')->assertSet('daysOff', ['2026-11-20'])
            ->assertSet('selections', ['R2', 'F1'])->assertViewHas('activeFilterCount', $activeCount);

        $this->assertSame($workspace, BidWorkspace::query()->sole()->toArray());
    }

    /** @return array<string, array{string, ?int, ?int, string, bool, int}> */
    public static function filterGroupResets(): array
    {
        return [
            'credit' => ['clearCreditFilters', null, 20, 'NRT', true, 5],
            'work pattern' => ['clearWorkPatternFilters', 100, null, 'NRT', true, 5],
            'airports' => ['clearLayoverFilters', 100, 20, '', false, 3],
        ];
    }

    public function test_filter_groups_share_live_results_across_tabs_and_panels_without_changing_bid_order(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F3'] = array_replace($payload['lines']['F1'], ['id' => 'F3', 'credit' => 4200]);
        $payload['lines']['F3']['calendar']['2026-11-15'] = ['status' => 'off', 'activity' => 'Off'];
        $package->update(['payload' => $payload]);
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->call('add', 'R2')->call('add', 'F1')->call('add', 'F3');

        foreach ([64, 70, 80] as $hours) {
            $page->assertSeeHtml('aria-label="Set minimum credit to '.$hours.' hours"');
        }
        foreach ([14, 15, 16] as $days) {
            $page->assertSeeHtml('aria-label="Set minimum days off to '.$days.' days"');
        }

        $page->set('minimumCredit', 70)->set('minimumDaysOff', 16)->set('sort', 'credit')
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F3'])
            ->assertViewHas('activeFilterSummary', 'Min credit 70h · Min days off 16')
            ->set('showPreferences', true)->set('showPreferences', false)->set('showList', true)->set('showList', false)
            ->set('tab', 'reserve')->assertViewHas('lines', [])
            ->set('tab', 'trips')->set('tab', 'flying')->refresh()
            ->assertSet('minimumCredit', 70)->assertSet('minimumDaysOff', 16)
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F3'])
            ->call('clearCreditFilters')->assertViewHas('activeFilterCount', 1)
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F3'])
            ->call('clearWorkPatternFilters')->assertViewHas('activeFilterCount', 0)
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F3', 'F1'])
            ->assertSet('selections', ['R2', 'F1', 'F3']);

        $this->assertSame(['R2', 'F1', 'F3'], BidWorkspace::query()->sole()->selections);
    }

    public function test_group_clears_remove_only_their_validation_errors_and_reset_filters_recovers_results(): void
    {
        BidPackage::factory()->create();
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->call('add', 'F1')->set('startCity', 'ANC')->set('daysOff', ['2026-11-01'])
            ->set('minimumCredit', 301)->set('minimumDaysOff', 32)->set('layoverCity', 'invalid')
            ->assertHasErrors(['minimumCredit' => 'between', 'minimumDaysOff' => 'between', 'layoverCity' => 'regex'])
            ->assertSee('The minimum credit field must be between 0 and 300.')
            ->assertSee('The minimum days off field must be between 0 and 31.')
            ->assertSee('The layover city field format is invalid.');

        $page->call('clearCreditFilters')->assertHasNoErrors('minimumCredit')
            ->assertHasErrors(['minimumDaysOff', 'layoverCity'])
            ->call('clearWorkPatternFilters')->assertHasNoErrors('minimumDaysOff')->assertHasErrors('layoverCity')
            ->call('resetFilters')->assertHasNoErrors()->assertSet('daysOff', [])->assertSet('startCity', '')
            ->assertViewHas('activeFilterCount', 0)->assertViewHas('activeFilterSummary', 'No active browsing filters')
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F1'])
            ->assertSet('selections', ['F1']);

        $workspace = BidWorkspace::query()->sole();
        $this->assertSame([], $workspace->preferences['days_off']);
        $this->assertSame('', $workspace->preferences['start_city']);
        $this->assertSame(['F1'], $workspace->selections);
    }

    public function test_zero_filters_are_active_but_browsing_filters_do_not_persist_or_follow_package_changes(): void
    {
        $package = BidPackage::factory()->create();
        $user = User::factory()->admin()->create();
        $page = Livewire::actingAs($user)->test(CrewBids::class)->call('add', 'F1')
            ->set('startCity', 'ANC')->set('daysOff', ['2026-11-20'])
            ->set('minimumCredit', 0)->set('minimumDaysOff', 0)
            ->assertViewHas('activeFilterCount', 2)->assertViewHas('activeFilterSummary', 'Min credit 0h · Min days off 0')
            ->set('layoverCity', 'NRT')->set('extendedLayoversOnly', true)->set('avoidShortLayovers', true)->set('selectedOnly', true);

        Livewire::test(CrewBids::class)->assertSet('minimumCredit', null)->assertSet('minimumDaysOff', null)
            ->assertSet('layoverCity', '')->assertSet('extendedLayoversOnly', false)->assertSet('avoidShortLayovers', false)
            ->assertSet('selectedOnly', false)->assertViewHas('activeFilterCount', 0)
            ->assertSet('startCity', 'ANC')->assertSet('daysOff', ['2026-11-20'])->assertSet('selections', ['F1']);
        $next = BidPackage::factory()->create(['period' => '2026-12']);
        $page->set('contextPackage', (string) $next->id)->call('changeContext')
            ->assertSet('packageId', $next->id)->assertSet('minimumCredit', null)->assertSet('minimumDaysOff', null)
            ->assertSet('layoverCity', '')->assertSet('extendedLayoversOnly', false)->assertSet('avoidShortLayovers', false)
            ->assertSet('selectedOnly', false)->assertViewHas('activeFilterCount', 0)->assertSet('selections', []);

        $workspace = BidWorkspace::query()->where('bid_package_id', $package->id)->sole();
        $this->assertSame(['goals' => [], 'days_off' => ['2026-11-20'], 'start_city' => 'ANC', 'initialized' => true], $workspace->preferences);
        $this->assertSame(['F1'], $workspace->selections);
    }

    public function test_controls_have_independent_disclosures_and_validation_descriptions_with_sort_outside_groups(): void
    {
        BidPackage::factory()->create();
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->set('minimumCredit', 301)->assertHasErrors('minimumCredit');
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$page->html());
        $xpath = new \DOMXPath($document);

        $this->assertCount(3, $xpath->query('//*[@id="bid-filters"]//details'));
        foreach (['bid-minimum-credit', 'bid-minimum-days-off', 'bid-layover-city'] as $id) {
            $this->assertCount(1, $xpath->query('//*[@id="'.$id.'"]/ancestor::details/summary'));
            $this->assertCount(1, $xpath->query('//label[@for="'.$id.'"]'));
            $this->assertCount(1, $xpath->query('//*[@id="'.$id.'-error"]'));
        }
        $this->assertSame('true', $xpath->evaluate('string(//*[@id="bid-minimum-credit"]/@aria-invalid)'));
        $this->assertSame('The minimum credit field must be between 0 and 300.', trim($xpath->evaluate('string(//*[@id="bid-minimum-credit-error"])')));
        $this->assertCount(0, $xpath->query('//*[@id="bid-sort"]/ancestor::details'));
        $this->assertCount(0, $xpath->query('//*[@id="bid-filters"]//details//input[@*[name()="wire:model.change.live"]="selectedOnly"]'));
    }
}
