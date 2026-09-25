<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OfflineFuelScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_a_source_backed_calculator_page(): void
    {
        $owner = User::factory()->admin()->create();
        $key = app(FlightPlanResultStore::class)->save($owner, $this->flightPlanResult());

        $this->actingAs($owner)
            ->get(route('flight-release.fuel-score', ['flightPlanKey' => $key]))
            ->assertOk()
            ->assertSeeText('Offline fuel calculator')
            ->assertSeeText('CKS241')
            ->assertSeeText('Off time (UTC, HHMM)')
            ->assertSeeHtml('<span class="whitespace-nowrap">Starting FOB at takeoff (<span x-text="fuelUnit?.toUpperCase() ?? \'unit unavailable\'"></span>)</span>')
            ->assertSeeText('ATA (UTC, HHMM)')
            ->assertSeeText('ETA vs ATA')
            ->assertSeeHtml('x-show="hasValidAta(waypoint)"')
            ->assertSeeText('AFOB')
            ->assertSeeText('TBO (source, cumulative)')
            ->assertSeeText('Planned FOB (FRMG)')
            ->assertSeeText('Actual burn (ABO, cumulative)')
            ->assertSeeText('Cumulative burn vs TBO')
            ->assertSeeHtml('x-text="fuelLabel(actualBurn(waypoint))"')
            ->assertSeeHtml('x-text="varianceLabel(burnVariance(waypoint))"')
            ->assertSeeText('Fuel on board')
            ->assertSeeText('Collapsed rows show planned FOB from the release.')
            ->assertSeeText('Estimated fuel at destination')
            ->assertDontSeeText('Calculated FOB')
            ->assertSeeInOrder([
                '>Waypoint</th>',
                '>ETA</th>',
                '>T/TME</th>',
                '>Fuel on board</th>',
                '>Estimated fuel at destination</th>',
            ], escape: false)
            ->assertSeeHtml('class="px-3 py-2 text-right">ETA</th>')
            ->assertSeeHtml('class="px-3 py-2 text-right">T/TME</th>')
            ->assertDontSeeHtml('>ETA (UTC)</th>')
            ->assertSeeHtml('scope="row" class="whitespace-nowrap px-3 py-1 text-left align-middle font-normal"')
            ->assertSeeHtml(':aria-expanded="waypoint.expanded.toString()"')
            ->assertSeeHtml('<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"')
            ->assertSeeHtml('class="h-4 w-4 transition-transform" :class="waypoint.expanded ? \'rotate-90\' : \'\'"')
            ->assertSeeHtml('x-show="waypoint.expanded" x-cloak')
            ->assertSeeHtml('colspan="5"')
            ->assertSeeHtml('class="whitespace-nowrap px-3 py-1 text-right align-middle" x-text="plannedEta(waypoint)"')
            ->assertSeeHtml('x-text="durationValue(waypoint.cumulativeDurationMinutes)"')
            ->assertSeeHtml('x-text="durationUnit(waypoint.cumulativeDurationMinutes)"')
            ->assertSeeHtml('x-text="sourceFuelValue(waypoint.remainingFuel)"')
            ->assertSeeHtml('x-text="destinationValue(waypoint)"')
            ->assertSeeHtml('x-text="destinationUnit(waypoint)"')
            ->assertSeeHtml('class="font-sans text-xs text-[#4A5568] dark:text-slate-400" x-text="sourceFuelUnit(waypoint.remainingFuel)"')
            ->assertSeeHtml('text-slate-400 dark:text-slate-600')
            ->assertSeeHtml('x-show="hasActualFob(waypoint)"')
            ->assertViewHas('calculator', function (array $calculator): bool {
                return $calculator['fuelUnit'] === 'lb'
                    && $calculator['takeoffFuel'] === ['amount' => 150000.0, 'unit' => 'lb']
                    && $calculator['estimatedLandingFuel'] === ['amount' => 30000.0, 'unit' => 'lb']
                    && $calculator['waypoints'][0]['tbo'] === '0011'
                    && $calculator['waypoints'][0]['remainingFuel'] === ['amount' => 120000.0, 'unit' => 'lb'];
            });
    }

    public function test_result_access_is_scoped_to_the_authenticated_verified_owner(): void
    {
        $owner = User::factory()->admin()->create();
        $key = app(FlightPlanResultStore::class)->save($owner, $this->flightPlanResult());
        $url = route('flight-release.fuel-score', ['flightPlanKey' => $key]);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->admin()->create())
            ->get($url)->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertNotFound();
        $this->actingAs($owner)->get(route('flight-release.fuel-score', ['flightPlanKey' => 'not-a-key']))->assertNotFound();
    }

    public function test_unavailable_fuel_fields_are_passed_as_missing_without_inventing_zero(): void
    {
        $owner = User::factory()->admin()->create();
        $result = $this->flightPlanResult();
        $result['flight_plan_data']['fuelPlan'] = null;
        $result['flight_plan_data']['waypoints'][0]['remainingFuel'] = null;
        $key = app(FlightPlanResultStore::class)->save($owner, $result);

        $this->actingAs($owner)
            ->get(route('flight-release.fuel-score', ['flightPlanKey' => $key]))
            ->assertOk()
            ->assertViewHas('calculator', function (array $calculator): bool {
                return $calculator['fuelUnit'] === null
                    && $calculator['takeoffFuel'] === null
                    && $calculator['estimatedLandingFuel'] === null
                    && $calculator['waypoints'][0]['remainingFuel'] === null;
            });
    }

    public function test_route_enforces_the_flight_release_feature_and_entitlement(): void
    {
        $owner = User::factory()->admin()->create();
        $key = app(FlightPlanResultStore::class)->save($owner, $this->flightPlanResult());
        $url = route('flight-release.fuel-score', ['flightPlanKey' => $key]);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        Config::set('features.flight_release.enabled', false);
        $this->actingAs($owner)->get($url)->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function flightPlanResult(): array
    {
        return ['flight_plan_data' => [
            'identity' => ['flightNumber' => 'CKS241', 'flightDate' => '2026-05-25'],
            'schedule' => [],
            'route' => ['departure' => 'PANC', 'destination' => 'KMIA'],
            'fuelPlan' => [
                'takeoff' => ['amount' => 150000.0, 'unit' => 'lb'],
                'estimatedLanding' => ['amount' => 30000.0, 'unit' => 'lb'],
            ],
            'waypoints' => [[
                'identifier' => 'FIX01',
                'tbo' => '0011',
                'coordinate' => 'N01 02.3 E004 05.6',
                'legDurationMinutes' => 5,
                'cumulativeDurationMinutes' => 11,
                'remainingFuel' => ['amount' => 120000.0, 'unit' => 'lb'],
            ]],
        ]];
    }
}
