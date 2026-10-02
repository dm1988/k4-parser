<?php

namespace Tests\Feature\Livewire\FlightPlanBrief\Tasks;

use App\Enums\FlightPlanTask;
use App\Livewire\FlightPlanBrief;
use App\Models\User;
use App\Services\FlightPlan\Extractor\ExtractFlightPlanData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\Feature\Livewire\FlightPlanBrief\FlightPlanBriefTestCase;

class FmsTest extends FlightPlanBriefTestCase
{
    public function test_fms_uses_a_dedicated_non_copyable_route_layout_while_jepp_preserves_its_existing_panel(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan(
                    identity: [
                        'flight_number' => 'CKS256',
                        'trip_number' => null,
                        'recall_number' => '62930',
                        'aircraft_type' => 'B777-200F',
                        'tail_number' => 'N774CK',
                        'flight_date' => '2026-05-25',
                        'release_revision' => null,
                    ],
                    route: ['distance_nautical_miles' => 5549],
                    fuel: [
                        'cost_index' => 200,
                        'ramp' => null,
                        'taxi' => null,
                        'takeoff' => null,
                        'trip' => null,
                        'contingency' => null,
                        'alternate' => ['amount' => 5600.0, 'unit' => 'lb'],
                        'final_reserve' => null,
                        'estimated_landing' => null,
                    ],
                    flightInit: [
                        'section_present' => true,
                        'filed_initial_altitude' => 'F330',
                        'fms_initial_altitude' => 'F290',
                    ],
                ));
        });
        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'));

        $flightPlanKey = $component->get('flightPlanKey');

        $component
            ->call('selectTask', FlightPlanTask::JeppPdPro->value)
            ->assertSet('activeTask', FlightPlanTask::JeppPdPro->value)
            ->assertSeeText('Extracted flight plan')
            ->assertSeeText('ETOPS critical points')
            ->assertSee('data-copy-target="flight-route-output"', escape: false)
            ->assertSee('DCT Q139', escape: false)
            ->assertSee(' TEST', escape: false)
            ->call('selectTask', FlightPlanTask::Fms->value)
            ->assertSet('activeTask', FlightPlanTask::Fms->value)
            ->assertSeeHtml('wire:key="flight-plan-task-panel-fms"')
            ->assertSeeText('FMS route setup')
            ->assertSeeText('Flight Number')
            ->assertSeeText('CKS256')
            ->assertSeeText('AC Type')
            ->assertSeeText('B777-200F')
            ->assertSeeText('Recall Number')
            ->assertSeeText('62930')
            ->assertSeeText('Cost Index')
            ->assertSeeText('200')
            ->assertSeeText('Distance to Destination')
            ->assertSeeText('5,549 NM')
            ->assertSeeText('FMS initial altitude')
            ->assertSeeText('FL290')
            ->assertDontSeeText('Planned Duration')
            ->assertSeeText('07h12m')
            ->assertSeeText('Alternate Airport Reserves')
            ->assertSeeText('5,600 LB')
            ->assertSeeText('PANC')
            ->assertSeeText('KMIA')
            ->assertSeeText('KRSW')
            ->assertSeeText('Miami International Airport')
            ->assertSeeText('Departure runway')
            ->assertSeeText('25R')
            ->assertSeeText('SUMMR2 SCTRR')
            ->assertSeeText('Arrival runway')
            ->assertSeeText('33R')
            ->assertSeeText('GUKDO GUKD2E')
            ->assertSeeTextInOrder([
                'AC Type',
                'B777-200F',
                'Flight Number',
                'CKS256',
                'Recall Number',
                '62930',
                'Alternate',
                'KRSW',
                'Distance to Destination',
                '5,549 NM',
                'Alternate Airport Reserves',
                '5,600 LB',
                'FMS initial altitude',
                'FL290',
                'Cost Index',
                '200',
            ])
            ->assertSeeHtml('data-fms-programming-tip')
            ->assertSeeTextInOrder([
                'Planned runways and procedures',
                'Tip: Remember to load winds and Route 2 copy after FMS activation.',
                'Airport context',
            ])
            ->assertSeeTextInOrder(['DCT', 'Q139', 'TEST'])
            ->assertDontSeeText('ETOPS critical points')
            ->assertDontSee('data-copy-target=', escape: false);

        $component
            ->call('$refresh')
            ->assertSet('activeTask', FlightPlanTask::Fms->value)
            ->assertSeeText('62930')
            ->assertDontSee('data-copy-target=', escape: false);

        $this->assertSame($flightPlanKey, $component->get('flightPlanKey'));
    }

    public function test_fms_renders_honest_missing_states_without_copy_controls(): void
    {
        Storage::fake('user_flight_releases');
        $extractedRouteData = [
            ...$this->flightPlan(),
            'alternate' => null,
            'alternate_airport' => null,
            'departure_runway' => null,
            'arrival_runway' => null,
            'departure_sid' => null,
            'arrival_star' => null,
            'initial_altitude' => '',
            'duration' => '',
        ];

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock) use ($extractedRouteData): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan(
                    extractedRouteData: $extractedRouteData,
                    identity: [
                        'flight_number' => 'CKS256',
                        'trip_number' => null,
                        'recall_number' => '5678',
                        'aircraft_type' => 'B777-200F',
                        'tail_number' => null,
                        'flight_date' => null,
                        'release_revision' => null,
                    ],
                ));
        });
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->call('selectTask', FlightPlanTask::Fms->value)
            ->assertSet('activeTask', FlightPlanTask::Fms->value)
            ->assertSeeText('FMS route setup')
            ->assertSeeText('No alternate airport listed.')
            ->assertSeeText('Departure runway')
            ->assertSeeText('SID')
            ->assertSeeText('Arrival runway')
            ->assertSeeText('STAR')
            ->assertSeeText('Not present in this release')
            ->assertDontSeeText('5678')
            ->assertDontSee('data-copy-target=', escape: false)
            ->assertSeeHtml('overflow-x-auto')
            ->assertSeeTextInOrder(['DCT', 'Q139', 'TEST']);
    }

    public function test_results_handle_missing_airport_and_alternate_details(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan([
                    'departure' => 'PANC',
                    'destination' => 'KMIA',
                    'alternate' => null,
                    'departure_airport' => null,
                    'destination_airport' => null,
                    'alternate_airport' => null,
                    'departure_runway' => null,
                    'arrival_runway' => null,
                    'departure_sid' => null,
                    'arrival_star' => null,
                    'etps' => [],
                    'eent_coordinates' => null,
                    'eexp_coordinates' => null,
                    'initial_altitude' => 'FL 330',
                    'duration' => '07h12m',
                    'route' => 'DCT TEST',
                ]));
        });
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSeeText('Airport details unavailable.')
            ->assertSeeText('No alternate airport listed.')
            ->assertDontSeeText('Departure runway')
            ->assertDontSeeText('ETOPS critical points');

        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());
    }
}
