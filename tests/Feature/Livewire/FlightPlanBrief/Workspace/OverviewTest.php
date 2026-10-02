<?php

namespace Tests\Feature\Livewire\FlightPlanBrief\Workspace;

use App\Enums\FlightPlanTask;
use App\Enums\FlightPlanTaskAvailability;
use App\Livewire\FlightPlanBrief;
use App\Models\User;
use App\Services\FlightPlan\Extractor\ExtractFlightPlanData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\Feature\Livewire\FlightPlanBrief\FlightPlanBriefTestCase;

class OverviewTest extends FlightPlanBriefTestCase
{
    public function test_overview_card_full_card_action_is_opt_out(): void
    {
        $componentData = [
            'task' => FlightPlanTask::Fms,
            'availability' => FlightPlanTaskAvailability::Available,
        ];

        $fullCard = $this->blade(
            '<x-flight-release.overview-card :task="$task" title="Route" icon="calculator" :availability="$availability">Summary</x-flight-release.overview-card>',
            $componentData,
        );

        $fullCard
            ->assertSeeHtml('<div aria-hidden="true" class="flex w-full')
            ->assertSeeHtml('aria-label="Program FMS"')
            ->assertSeeHtml('absolute inset-0 z-10 cursor-pointer rounded-xl')
            ->assertSeeHtml('focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#C5A059]')
            ->assertSeeHtml('disabled:cursor-wait disabled:bg-white/40 dark:disabled:bg-slate-950/40');

        $footerActionCard = $this->blade(
            '<x-flight-release.overview-card :task="$task" title="Route" icon="calculator" :availability="$availability" :full-card-action="false">Summary</x-flight-release.overview-card>',
            $componentData,
        );

        $footerActionCard
            ->assertSeeHtml('wire:click="selectTask(\'fms\')"')
            ->assertDontSeeHtml('absolute inset-0 z-10 cursor-pointer rounded-xl')
            ->assertDontSeeHtml('<div aria-hidden="true" class="flex w-full');
    }

    public function test_overview_presents_complete_source_backed_values_and_links_to_detail_tasks_without_reparsing(): void
    {
        Storage::fake('user_flight_releases');
        $user = User::factory()->admin()->create();

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan(
                    identity: [
                        'flight_number' => 'CKS241',
                        'trip_number' => '1234',
                        'recall_number' => '5678',
                        'aircraft_type' => 'B777-200F',
                        'tail_number' => 'N774CK',
                        'flight_date' => '2026-05-25',
                        'release_revision' => '3',
                    ],
                    schedule: [
                        'etd_utc' => '2026-05-25T18:30:00Z',
                        'eta_utc' => '2026-05-26T02:15:00Z',
                        'block_duration' => null,
                        'report_time_utc' => null,
                        'duty_end_utc' => null,
                        'slot_source_text' => 'APPROVED SLOT TIMES: DEP PANC @ 1845Z ARR KMIA @ 0230Z (+/- 30 MIN)',
                        'slots' => [[
                            'direction' => 'departure',
                            'airport' => 'PANC',
                            'instant_utc' => '2026-05-25T18:45:00Z',
                            'source_time' => '1845Z',
                            'tolerance_minutes' => 30,
                        ], [
                            'direction' => 'arrival',
                            'airport' => 'KMIA',
                            'instant_utc' => '2026-05-26T02:30:00Z',
                            'source_time' => '0230Z',
                            'tolerance_minutes' => 30,
                        ]],
                        'slot_times_utc' => ['2026-05-25T18:45:00Z', '2026-05-26T02:30:00Z'],
                    ],
                    route: ['distance_nautical_miles' => 4000],
                    fuel: [
                        'ramp' => ['amount' => 120000.0, 'unit' => 'lb'],
                        'taxi' => ['amount' => 2000.0, 'unit' => 'lb'],
                        'takeoff' => null,
                        'trip' => null,
                        'contingency' => null,
                        'alternate' => null,
                        'final_reserve' => null,
                        'estimated_landing' => null,
                    ],
                    waypoints: [[
                        'identifier' => 'FIX01',
                        'coordinate' => 'N01 02.3 E004 05.6',
                        'time' => '005',
                        'total_time' => '00.11',
                        'remaining_fuel' => '1477',
                    ], [
                        'identifier' => 'FIX01',
                        'coordinate' => 'N02 03.4 E005 06.7',
                        'time' => null,
                        'total_time' => null,
                        'remaining_fuel' => null,
                    ]],
                    flightInit: [
                        'section_present' => true,
                        'filed_initial_altitude' => 'F330',
                        'fms_initial_altitude' => 'F290',
                    ],
                    etops: [
                        'section_present' => true,
                        'applicability' => 'confirmed_etops',
                    ],
                    generalDeclaration: ['section_present' => true],
                    releaseAuthorization: ['operations_specification' => 'b44'],
                ));
        });
        $component = Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSet('activeTask', FlightPlanTask::Overview->value)
            ->assertSeeText('CKS241')
            ->assertSeeText('May 25, 2026')
            ->assertSeeText('B777-200F')
            ->assertSeeText('N774CK')
            ->assertSeeText('PANC')
            ->assertSeeText('KMIA')
            ->assertSeeText('KRSW')
            ->assertDontSeeText('ETD (UTC)')
            ->assertDontSeeText('ETA (UTC)')
            ->assertSeeText('Initial altitude')
            ->assertSeeText('FL330')
            ->assertSeeText('4,000 NM')
            ->assertSeeText('120.0')
            ->assertSeeText('k lbs')
            ->assertSeeText('Ramp Fuel')
            ->assertSeeText('2.0k lbs taxi fuel')
            ->assertSeeHtml('aria-label="Ramp fuel: 120,000 pounds"')
            ->assertSeeHtml('font-mono text-4xl font-black')
            ->assertSeeHtml('text-[#4A5568] dark:text-slate-300')
            ->assertSeeText('approved slot times')
            ->assertSeeHtml('aria-label="2 approved slot times"')
            ->assertSeeHtml('aria-label="Slot Times: 2 approved slots"')
            ->assertSeeHtml('rounded-full bg-amber-100')
            ->assertSeeText('1 ETP point')
            ->assertSeeText('ETOPS time: Not present in this release')
            ->assertSeeText('Program FMS')
            ->assertSeeText('Review Slot Times')
            ->assertSeeText('Score Fuel')
            ->assertSeeText('Review ETOPS')
            ->assertSeeHtml('title="OpSpec B44 Authorized"')
            ->assertSeeHtml('wire:key="flight-plan-task-nav-fuel_score-b44"')
            ->assertSeeHtml('bg-amber-500/10')
            ->assertSeeText('Operational support status')
            ->assertSeeText('GENDEC')
            ->assertSeeHtml('id="overview-gendec-card"')
            ->assertSeeText('GENDEC found in flight plan.')
            ->assertDontSeeText('Flight plan filing')
            ->assertSeeText('Weather / RAIM')
            ->assertSeeText('MEL / CDL restrictions')
            ->assertSeeText('No active MEL/CDL restrictions')
            ->assertDontSeeText('On plan')
            ->assertDontSeeText('Dispatchable');

        $this->assertSame(
            1,
            preg_match(
                '/<article[^>]*wire:key="flight-plan-overview-card-fms"[^>]*>.*?<\/article>/s',
                $component->html(),
                $routeOverviewCard,
            ),
        );
        $this->assertStringNotContainsString('Departure', $routeOverviewCard[0]);
        $this->assertSame(1, preg_match('/<article[^>]*wire:key="flight-plan-overview-card-fuel_score"[^>]*>.*?<\/article>/s', $component->html(), $fuelOverviewCard));
        $this->assertStringContainsString('aria-label="Score Fuel"', $fuelOverviewCard[0]);
        $this->assertStringContainsString('Ramp Fuel', $fuelOverviewCard[0]);
        $this->assertStringContainsString('2.0k lbs taxi fuel', $fuelOverviewCard[0]);
        $this->assertStringContainsString('aria-label="Ramp fuel: 120,000 pounds"', $fuelOverviewCard[0]);
        $this->assertStringContainsString('title="OpSpec B44 Authorized"', $fuelOverviewCard[0]);
        $this->assertStringContainsString('B44', $fuelOverviewCard[0]);
        $this->assertStringNotContainsString('title="OpSpec B44 Authorized"', $routeOverviewCard[0]);
        $this->assertStringNotContainsString('Destination', $routeOverviewCard[0]);
        $this->assertStringContainsString('Alternate', $routeOverviewCard[0]);
        $this->assertStringContainsString('Initial altitude', $routeOverviewCard[0]);
        $this->assertStringContainsString('Distance', $routeOverviewCard[0]);
        $this->assertStringContainsString('<div aria-hidden="true" class="flex w-full', $routeOverviewCard[0]);
        $this->assertStringContainsString('aria-label="Program FMS"', $routeOverviewCard[0]);
        $this->assertStringContainsString('absolute inset-0 z-10 cursor-pointer rounded-xl', $routeOverviewCard[0]);
        $this->assertSame(1, substr_count($routeOverviewCard[0], 'wire:click="selectTask(\'fms\')"'));

        $this->assertSame(
            1,
            preg_match(
                '/<article[^>]*wire:key="flight-plan-overview-card-review_mel_cdl"[^>]*>.*?<\/article>/s',
                $component->html(),
                $emptyMaintenanceOverviewCard,
            ),
        );
        $this->assertStringNotContainsString('wire:click="selectTask(\'review_mel_cdl\')"', $emptyMaintenanceOverviewCard[0]);

        $flightPlanKey = $component->get('flightPlanKey');
        $detailTasks = [
            FlightPlanTask::Fms,
            FlightPlanTask::SlotTimes,
            FlightPlanTask::FuelScore,
            FlightPlanTask::Etops,
        ];

        foreach ($detailTasks as $task) {
            $component
                ->call('selectTask', FlightPlanTask::Overview->value)
                ->assertSeeHtml('wire:key="flight-plan-overview-card-'.$task->value.'"')
                ->assertSeeHtml('aria-label="'.$task->actionLabel().'"')
                ->assertSeeHtml('absolute inset-0 z-10 cursor-pointer rounded-xl')
                ->call('selectTask', $task->value)
                ->assertSet('activeTask', $task->value);
        }

        $component
            ->assertSeeHtml('title="OpSpec B44 Authorized"')
            ->assertSeeHtml('wire:key="flight-plan-task-nav-fuel_score-b44"');

        $component
            ->call('selectTask', FlightPlanTask::SlotTimes->value)
            ->assertSeeText('Approved slot times')
            ->assertSeeText('All displayed times are UTC')
            ->assertSeeText('Departure')
            ->assertSeeText('PANC')
            ->assertSeeText('May 25, 2026')
            ->assertSeeText('1845Z')
            ->assertSeeText('Time (UTC)')
            ->assertSeeText('Approved window')
            ->assertSeeText('May 25, 1815Z–May 25, 1915Z UTC')
            ->assertSeeText('± 30 min')
            ->assertSeeText('Planned departure comparison')
            ->assertSeeText('May 25, 1830Z UTC')
            ->assertSeeText('Planned ETD is within the confirmed window')
            ->assertSeeText('Buffer from earliest slot time')
            ->assertSeeText('15 min')
            ->assertSeeText('Planned arrival comparison')
            ->assertSeeText('May 26, 0215Z UTC')
            ->assertSeeText('Planned ETA is within the confirmed window')
            ->assertSeeText('Confirmed window')
            ->assertSeeText('Extracted slot text')
            ->assertSeeHtml('<details open')
            ->assertSeeText('APPROVED SLOT TIMES: DEP PANC @ 1845Z ARR KMIA @ 0230Z (+/- 30 MIN)')
            ->assertSeeText('Local times, permits, and statuses are not inferred')
            ->call('selectTask', FlightPlanTask::FuelScore->value)
            ->assertSeeText('Fuel summary')
            ->assertSeeText('Ramp')
            ->assertSeeText('120.0')
            ->assertSeeText('k lbs')
            ->assertSeeText('Taxi')
            ->assertSeeText('Not present in this release')
            ->assertSeeText('No score or status inferred')
            ->assertSeeText('does not calculate a fuel score')
            ->assertSeeText('Waypoint fuel')
            ->assertSeeText('Open offline fuel calculator')
            ->assertSeeHtml('target="_blank" rel="noopener noreferrer"')
            ->assertSeeText('Remaining fuel')
            ->assertSee('147.7 k lbs')
            ->assertSee('FIX01')
            ->assertDontSeeText('Off time (UTC)')
            ->assertDontSeeText('Planned ETA')
            ->assertDontSeeText('More…')
            ->assertDontSeeHtml('x-data="waypointFuelMonitor')
            ->assertDontSeeText('Coordinate')
            ->assertDontSeeText('Its dedicated operational layout is scheduled in the next focused task.');

        $this->assertSame($flightPlanKey, $component->get('flightPlanKey'));
    }

    public function test_overview_labels_sparse_values_without_inventing_zero_or_supported_statuses(): void
    {
        Storage::fake('user_flight_releases');

        $extractedRouteData = [
            ...$this->flightPlan(),
            'alternate' => null,
            'departure_airport' => null,
            'destination_airport' => null,
            'alternate_airport' => null,
            'etps' => [],
            'eent_coordinates' => null,
            'eexp_coordinates' => null,
            'initial_altitude' => '',
        ];

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock) use ($extractedRouteData): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan(
                    extractedRouteData: $extractedRouteData,
                    releaseAuthorization: ['operations_specification' => 'b43'],
                ));
        });
        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSet('activeTask', FlightPlanTask::Overview->value)
            ->assertSeeText('Not present in this release')
            ->assertSeeText('No alternate airport listed.')
            ->assertSeeText('GENDEC')
            ->assertSeeText('Weather / RAIM')
            ->assertSeeHtml('bg-red-100 text-red-900 dark:bg-red-400/15 dark:text-red-200')
            ->assertDontSeeText('Non ETOPS')
            ->assertDontSeeHtml('wire:key="flight-plan-overview-card-etops"')
            ->assertDontSeeHtml('wire:key="flight-plan-task-nav-etops"')
            ->assertDontSeeHtml('title="OpSpec B44 Authorized"')
            ->assertDontSeeHtml('wire:key="flight-plan-task-nav-fuel_score-b44"')
            ->assertDontSeeText('0 LB')
            ->assertDontSeeText('0 KG')
            ->assertDontSeeText('On plan')
            ->assertDontSeeText('Dispatchable');

        $this->assertSame(1, preg_match('/<article[^>]*wire:key="flight-plan-overview-card-fuel_score"[^>]*>.*?<\/article>/s', $component->html(), $fuelOverviewCard));
        $this->assertStringContainsString('Not present in this release', $fuelOverviewCard[0]);
        $this->assertStringNotContainsString('aria-label="Ramp fuel:', $fuelOverviewCard[0]);
        $this->assertStringNotContainsString('k lbs', $fuelOverviewCard[0]);
        $this->assertStringNotContainsString('taxi fuel', $fuelOverviewCard[0]);

        $component
            ->call('selectTask', FlightPlanTask::Etops->value)
            ->assertSet('activeTask', FlightPlanTask::Overview->value)
            ->assertDontSeeHtml('wire:key="flight-plan-task-panel-etops"')
            ->assertDontSeeText('ETOPS source data');
    }
}
