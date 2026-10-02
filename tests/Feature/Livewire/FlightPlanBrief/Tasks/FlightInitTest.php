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

class FlightInitTest extends FlightPlanBriefTestCase
{
    public function test_flight_init_renders_acars_values_and_crew_employee_numbers_without_copy_controls_or_reparsing(): void
    {
        Storage::fake('user_flight_releases');
        $user = User::factory()->admin()->create();

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan(
                    identity: [
                        'flight_number' => 'CKS256',
                        'trip_number' => '109546',
                        'recall_number' => null,
                        'aircraft_type' => 'B777-300ER',
                        'tail_number' => 'N770CK',
                        'flight_date' => '2026-05-25',
                        'release_revision' => null,
                    ],
                    schedule: [
                        'etd_utc' => '2026-05-25T13:55:00Z',
                        'eta_utc' => null,
                        'block_duration' => '07h12m',
                        'report_time_utc' => null,
                        'duty_end_utc' => null,
                        'slot_times_utc' => [],
                    ],
                    fuel: [
                        'ramp' => ['amount' => 225500.0, 'unit' => 'lb'],
                        'taxi' => null,
                        'takeoff' => null,
                        'trip' => null,
                        'contingency' => null,
                        'alternate' => null,
                        'final_reserve' => null,
                        'estimated_landing' => null,
                    ],
                    crewMembers: [
                        ['name' => 'MORGAN A', 'role' => 'PIC', 'base' => null, 'employee_number' => '4387'],
                        ['name' => 'GONZALEZ D', 'role' => 'SIC/FO', 'base' => null, 'employee_number' => '72914', 'high_mins' => true],
                        ['name' => 'FOSTER B', 'role' => 'IRP', 'base' => null, 'employee_number' => '73521'],
                        ['name' => 'MCCULLOUGH M', 'role' => 'IRP', 'base' => null, 'employee_number' => '73642'],
                        ['name' => 'BENNETT B', 'role' => 'MX', 'base' => null, 'employee_number' => '5826'],
                        ['name' => 'GARCIA T', 'role' => 'LM', 'base' => null, 'employee_number' => '1957'],
                    ],
                    flightInit: [
                        'section_present' => true,
                        'acars_init_date' => '11',
                        'filed_initial_altitude' => 'F330',
                        'fms_initial_altitude' => 'F290',
                    ],
                ));
        });
        $component = Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'));

        $flightPlanKey = $component->get('flightPlanKey');

        $component
            ->call('selectTask', FlightPlanTask::FlightInit->value)
            ->assertSet('activeTask', FlightPlanTask::FlightInit->value)
            ->assertSeeHtml('wire:key="flight-plan-task-panel-flight_init"')
            ->assertSeeText('Initialization fields')
            ->assertSeeTextInOrder(['PANC', 'May 25, 2026', '1355z'])
            ->assertSeeHtml('<span class="text-[10px]">z</span>')
            ->assertSeeHtml('max-w-xl')
            ->assertSeeHtmlInOrder([
                'id="flight-init-acars-init-date"',
                'id="flight-init-departure"',
                'id="flight-init-arrival"',
                'id="flight-init-alternate"',
                'id="flight-init-duration"',
                'id="flight-init-etd"',
                'id="flight-init-ramp-fuel"',
                'id="flight-init-crew-heading"',
            ])
            ->assertSeeTextInOrder([
                'ACARS init date',
                '11',
                'Departure airport',
                'PANC',
                'Arrival airport',
                'KMIA',
                'Alternate airport',
                'KRSW',
                'Flight duration',
                '07h12m',
                'ETD (UTC)',
                '1355Z',
                'Estimated ramp fuel',
                '225,500 LB',
                'Crew list',
            ])
            ->assertDontSeeText('Filed initial altitude')
            ->assertDontSeeText('FMS initial altitude')
            ->assertSeeText('not derived from the release flight date')
            ->assertSeeText('MORGAN A')
            ->assertSeeText('4387')
            ->assertSeeText('GONZALEZ D')
            ->assertSeeText('72914')
            ->assertSeeText('High mins')
            ->assertSeeText('FOSTER B')
            ->assertSeeText('73521')
            ->assertSeeText('MCCULLOUGH M')
            ->assertSeeText('73642')
            ->assertSeeText('BENNETT B')
            ->assertSeeText('5826')
            ->assertSeeText('GARCIA T')
            ->assertSeeText('1957')
            ->assertDontSee('data-copy-target="flight-init-', escape: false)
            ->assertDontSee('data-copy-label="MORGAN A employee number"', escape: false)
            ->assertSeeText('Flight Init source fragments remain private');

        $component
            ->call('$refresh')
            ->assertSet('activeTask', FlightPlanTask::FlightInit->value)
            ->assertSeeText('GONZALEZ D')
            ->assertSeeText('11')
            ->assertDontSee('data-copy-target="flight-init-', escape: false);

        $this->assertSame($flightPlanKey, $component->get('flightPlanKey'));
    }
}
