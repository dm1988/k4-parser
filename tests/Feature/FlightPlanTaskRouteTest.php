<?php

namespace Tests\Feature;

use App\Enums\FlightPlanTask;
use App\Livewire\FlightPlanBrief;
use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class FlightPlanTaskRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_pages_are_routed_as_full_page_livewire_components(): void
    {
        foreach (['flight-release.index', 'flight-release.task'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route);
            $this->assertSame(FlightPlanBrief::class, $route->getAction('livewire_component'));
        }
    }

    public function test_the_canonical_base_distinguishes_upload_and_saved_result_states(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('flight-release.index'))
            ->assertOk()
            ->assertSeeText('Drop your flight plan here');

        app(FlightPlanResultStore::class)->save($user, $this->flightPlanResult());

        $this->get(route('flight-release.index'))
            ->assertRedirect(route('flight-release.task', ['task' => 'overview']));
    }

    public function test_a_canonical_task_route_restores_the_matching_panel(): void
    {
        $user = User::factory()->admin()->create();
        app(FlightPlanResultStore::class)->save($user, $this->flightPlanResult());

        $this->actingAs($user)
            ->get(route('flight-release.task', ['task' => 'fms']))
            ->assertOk()
            ->assertSeeHtml('wire:key="flight-plan-task-panel-fms"')
            ->assertSeeText('FMS route setup')
            ->assertSeeHtml('aria-current="page"');

        $this->get(route('flight-release.task', ['task' => 'notes']))
            ->assertOk()
            ->assertSeeHtml('wire:key="flight-plan-task-panel-notes"')
            ->assertSeeText('Route test note.');
    }

    public function test_task_routes_without_a_saved_result_return_to_the_upload_url(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('flight-release.task', ['task' => 'weather']))
            ->assertRedirect(route('flight-release.index'));
    }

    public function test_hidden_and_unknown_tasks_return_not_found(): void
    {
        $user = User::factory()->admin()->create();
        app(FlightPlanResultStore::class)->save($user, $this->flightPlanResult());

        $this->actingAs($user)
            ->get(route('flight-release.task', ['task' => 'slot-times']))
            ->assertNotFound();

        $this->get(route('flight-release.task', ['task' => 'etops']))
            ->assertNotFound();

        $this->get('/flight-plan-brief/unknown')->assertNotFound();
        $this->get('/flight-plan-brief/fuel-score')->assertNotFound();
    }

    public function test_conditional_task_routes_open_when_the_release_exposes_them(): void
    {
        $user = User::factory()->admin()->create();
        $result = $this->flightPlanResult();
        $result['flight_plan_data']['schedule']['slotTimesUtc'] = ['2026-05-25T18:45:00+00:00'];
        $result['flight_plan_data']['schedule']['slots'] = [[
            'direction' => 'departure',
            'airport' => 'PANC',
            'instantUtc' => '2026-05-25T18:45:00+00:00',
            'sourceTime' => '1845Z',
            'toleranceMinutes' => 30,
        ]];
        $result['flight_plan_data']['etops'] = [
            'sectionPresent' => true,
            'applicability' => 'confirmed_etops',
            'ratingMinutes' => 180,
        ];
        app(FlightPlanResultStore::class)->save($user, $result);

        $this->actingAs($user)
            ->get(route('flight-release.task', ['task' => 'slot-times']))
            ->assertOk()
            ->assertSeeHtml('wire:key="flight-plan-task-panel-slot_times"');

        $this->get(route('flight-release.task', ['task' => 'etops']))
            ->assertOk()
            ->assertSeeHtml('wire:key="flight-plan-task-panel-etops"');
    }

    public function test_each_legacy_task_path_redirects_to_its_canonical_route(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (FlightPlanTask::cases() as $task) {
            $this->get(route('flight-release.legacy.task', ['task' => $task->routeSlug()]))
                ->assertRedirect(route('flight-release.task', ['task' => $task->routeSlug()]));
        }

        $this->get(route('flight-release.legacy.index'))
            ->assertRedirect(route('flight-release.index'));

        $this->get('/flight-route-extractor/unknown')->assertNotFound();
    }

    public function test_the_legacy_calculator_redirect_preserves_owner_checks_and_the_result_key(): void
    {
        $owner = User::factory()->admin()->create();
        $key = app(FlightPlanResultStore::class)->save($owner, $this->flightPlanResult());
        $legacyUrl = route('flight-release.legacy.fuel-score', ['flightPlanKey' => $key]);

        $this->actingAs(User::factory()->admin()->create())
            ->get($legacyUrl)
            ->assertNotFound();

        $this->actingAs($owner)
            ->get($legacyUrl)
            ->assertRedirect(route('flight-release.fuel-score', ['flightPlanKey' => $key]));
    }

    public function test_fuel_task_and_keyed_calculator_routes_do_not_collide(): void
    {
        $owner = User::factory()->admin()->create();
        $key = app(FlightPlanResultStore::class)->save($owner, $this->flightPlanResult());

        $this->actingAs($owner)
            ->get(route('flight-release.task', ['task' => 'fuel']))
            ->assertOk()
            ->assertSeeHtml('wire:key="flight-plan-task-panel-fuel_score"');

        $this->get(route('flight-release.fuel-score', ['flightPlanKey' => $key]))
            ->assertOk()
            ->assertSeeText('Offline fuel calculator');
    }

    public function test_livewire_task_selection_navigates_to_the_canonical_route(): void
    {
        $user = User::factory()->admin()->create();
        app(FlightPlanResultStore::class)->save($user, $this->flightPlanResult());

        Livewire::actingAs($user)
            ->test(FlightPlanBrief::class, [
                'task' => 'overview',
                'usesTaskRoutes' => true,
            ])
            ->call('selectTask', FlightPlanTask::Fms->value)
            ->assertSet('activeTask', FlightPlanTask::Fms->value)
            ->assertRedirect(route('flight-release.task', ['task' => 'fms']));
    }

    public function test_reset_and_missing_result_transitions_return_to_the_upload_route(): void
    {
        $user = User::factory()->admin()->create();
        $resultStore = app(FlightPlanResultStore::class);
        $key = $resultStore->save($user, $this->flightPlanResult());

        Livewire::actingAs($user)
            ->test(FlightPlanBrief::class, [
                'task' => 'overview',
                'usesTaskRoutes' => true,
            ])
            ->call('extractAnotherFlightPlan')
            ->assertSet('flightPlanKey', null)
            ->assertRedirect(route('flight-release.index'));

        $this->assertNull($resultStore->get($user, $key));

        $replacementKey = $resultStore->save($user, $this->flightPlanResult());
        $component = Livewire::actingAs($user)
            ->test(FlightPlanBrief::class, [
                'task' => 'overview',
                'usesTaskRoutes' => true,
            ]);

        $resultStore->delete($user, $replacementKey);

        $component
            ->call('$refresh')
            ->assertSet('flightPlanKey', null)
            ->assertRedirect(route('flight-release.index'));
    }

    /** @return array<string, mixed> */
    private function flightPlanResult(): array
    {
        return [
            'flight_plan_data' => [
                'identity' => [
                    'flightNumber' => 'CKS241',
                    'tripNumber' => null,
                    'recallNumber' => null,
                    'aircraftType' => 'B777-200F',
                    'tailNumber' => 'N774CK',
                    'flightDate' => '2026-05-25',
                    'releaseRevision' => null,
                ],
                'schedule' => [
                    'etdUtc' => '2026-05-25T18:30:00+00:00',
                    'etaUtc' => '2026-05-26T02:15:00+00:00',
                    'blockDuration' => '07h45m',
                    'reportTimeUtc' => null,
                    'dutyEndUtc' => null,
                    'slotTimesUtc' => [],
                ],
                'route' => [
                    'departure' => 'PANC',
                    'destination' => 'KMIA',
                    'alternate' => 'KRSW',
                    'departureAirport' => null,
                    'destinationAirport' => null,
                    'alternateAirport' => null,
                    'route' => 'DCT TEST',
                    'departureRunway' => null,
                    'arrivalRunway' => null,
                    'departureSid' => null,
                    'arrivalStar' => null,
                    'distanceNauticalMiles' => 4000,
                ],
                'fuelPlan' => [
                    'takeoff' => ['amount' => 150000.0, 'unit' => 'lb'],
                    'estimatedLanding' => ['amount' => 30000.0, 'unit' => 'lb'],
                ],
                'dispatcherNotes' => [['text' => 'Route test note.']],
                'waypoints' => [],
            ],
        ];
    }
}
