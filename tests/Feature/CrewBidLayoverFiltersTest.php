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

class CrewBidLayoverFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_combine_city_and_duration_and_preserve_selected_lines(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        foreach (['F1', 'F2', 'F3', 'F4', 'F5'] as $id) {
            $payload['lines'][$id] = array_replace($payload['lines']['F1'], ['id' => $id, 'pairings' => [['id' => $id, 'date' => '2026-11-01']]]);
        }
        $payload['lines']['F1']['pairings'][] = ['id' => 'short', 'date' => '2026-11-01'];
        $payload['lines']['F5']['pairings'] = [];
        $payload['trips'] = [
            'F1' => $this->trip('F1', 'NRT', '2026-11-02', '10:00'),
            'short' => $this->trip('short', 'ANC', '2026-11-01', '19:59'),
            'F2' => $this->trip('F2', 'NRT', '2026-11-02', '10:00'),
            'F3' => $this->trip('F3', 'ANC', '2026-11-02', '10:00'),
        ];
        $package->update(['payload' => $payload]);

        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->call('add', 'F1')->call('add', 'F3')
            ->set('extendedLayoversOnly', true)
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F1', 'F2', 'F3'])
            ->set('layoverCity', 'NRT')
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F1', 'F2'])
            ->set('avoidShortLayovers', true)
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F2'] && $lines[0]['trip_details'][0]['legs'][0]['layover_matches_filter'])
            ->assertViewHas('layoverCities', ['ANC', 'NRT'])->assertSee('Matches layover filter')
            ->assertSet('selections', ['F1', 'F3'])
            ->assertViewHas('selected', fn (array $lines): bool => array_column($lines, 'id') === ['F1', 'F3'])
            ->call('clearLayoverFilters')
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F1', 'F2', 'F3', 'F4', 'F5'])
            ->assertDontSee('Matches layover filter')
            ->set('layoverCity', 'ANC')
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F1', 'F3'])
            ->set('extendedLayoversOnly', true)->set('avoidShortLayovers', true)->call('resetFilters')
            ->assertSet('layoverCity', '')->assertSet('extendedLayoversOnly', false)->assertSet('avoidShortLayovers', false);

        $this->assertSame(['F1', 'F3'], BidWorkspace::query()->sole()->selections);
        $this->assertSame($payload, $package->fresh()->payload);
    }

    #[DataProvider('durationBoundaries')]
    public function test_filters_use_exact_scheduled_duration_boundaries(string $date, string $departure, bool $extended, bool $avoidsShort): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [['id' => '749', 'date' => '2026-11-01']];
        $payload['trips']['749'] = $this->trip('749', 'NRT', $date, $departure);
        $package->update(['payload' => $payload]);

        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->set('extendedLayoversOnly', true)
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ($extended ? ['F1'] : []))
            ->set('extendedLayoversOnly', false)->set('avoidShortLayovers', true)
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ($avoidsShort ? ['F1'] : []));
    }

    /** @return array<string, array{string, string, bool, bool}> */
    public static function durationBoundaries(): array
    {
        return [
            'zero gap' => ['2026-11-01', '10:00', false, false],
            'under ten hours' => ['2026-11-01', '19:59', false, false],
            'exactly ten hours' => ['2026-11-01', '20:00', false, true],
            'under twenty-four hours' => ['2026-11-02', '09:59', false, true],
            'exactly twenty-four hours' => ['2026-11-02', '10:00', true, true],
        ];
    }

    #[DataProvider('uncertainConnections')]
    public function test_avoid_short_excludes_uncertain_connections_even_with_a_known_extended_layover(string $uncertainty): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [['id' => '749', 'date' => '2026-11-01'], ['id' => '750', 'date' => '2026-11-01']];
        $payload['trips']['749'] = $this->trip('749', 'NRT', '2026-11-02', '10:00');
        if ($uncertainty !== 'missing') {
            $trip = $this->trip('750', 'ANC', '2026-11-02', '10:00');
            if ($uncertainty === 'no legs') {
                $trip['legs'] = [];
            } elseif ($uncertainty === 'overlap') {
                $trip['legs'][1]['date'] = '2026-11-01';
                $trip['legs'][1]['departure'] = '09:59';
            } else {
                $trip['legs'][1]['origin'] = 'CVG';
            }
            $payload['trips']['750'] = $trip;
        }
        $package->update(['payload' => $payload]);

        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->set('extendedLayoversOnly', true)
            ->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F1'])
            ->set('avoidShortLayovers', true)->assertSee('No lines match these filters.')
            ->assertViewHas('lines', []);
    }

    /** @return array<string, array{string}> */
    public static function uncertainConnections(): array
    {
        return ['missing trip' => ['missing'], 'empty trip' => ['no legs'], 'overlapping times' => ['overlap'], 'different airports' => ['airport change']];
    }

    public function test_invalid_layover_airport_reports_validation_and_context_switch_clears_filters(): void
    {
        $package = BidPackage::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->set('layoverCity', '<script>')->assertHasErrors(['layoverCity' => 'regex'])
            ->assertSee('The layover city field format is invalid.')
            ->set('layoverCity', 'NRT')->set('extendedLayoversOnly', true)->set('avoidShortLayovers', true)
            ->set('contextPackage', (string) $package->id)->call('changeContext')
            ->assertSet('layoverCity', '')->assertSet('extendedLayoversOnly', false)->assertSet('avoidShortLayovers', false);
    }

    /** @return array<string, mixed> */
    private function trip(string $id, string $airport, string $nextDate, string $departure): array
    {
        return [
            'id' => $id, 'start_date' => '2026-11-01', 'end_date' => $nextDate,
            'start_city' => 'CVG', 'end_city' => 'YHM', 'report' => '07:00', 'release' => '23:30',
            'legs' => [
                ['date' => '2026-11-01', 'origin' => 'CVG', 'destination' => $airport, 'departure' => '08:00', 'arrival' => '10:00', 'deadhead' => false, 'rest_after' => '8:00'],
                ['date' => $nextDate, 'origin' => $airport, 'destination' => 'YHM', 'departure' => $departure, 'arrival' => '23:00', 'deadhead' => false],
            ],
        ];
    }
}
