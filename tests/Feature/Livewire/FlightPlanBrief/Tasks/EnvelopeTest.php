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

class EnvelopeTest extends FlightPlanBriefTestCase
{
    public function test_envelope_hides_the_confirmed_tlr_presentation_and_keeps_shared_context_without_reparsing(): void
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
                        'aircraft_type' => 'B777-200F',
                        'tail_number' => 'N774CK',
                        'flight_date' => '2026-05-25',
                        'release_revision' => null,
                    ],
                    crewMembers: [
                        ['name' => 'MORGAN A', 'role' => 'PIC', 'base' => null, 'employee_number' => '4387'],
                        ['name' => 'RIVERA D', 'role' => 'SIC/FO', 'base' => null, 'employee_number' => '72914'],
                        ['name' => 'FOSTER B', 'role' => 'IRP', 'base' => null, 'employee_number' => '73521'],
                        ['name' => 'MCCULLOUGH M', 'role' => 'IRP', 'base' => null, 'employee_number' => '73642'],
                        ['name' => 'BENNETT B', 'role' => 'MX', 'base' => null, 'employee_number' => '5826'],
                        ['name' => 'GARCIA T', 'role' => 'LM', 'base' => null, 'employee_number' => '1957'],
                    ],
                    takeoffLandingReport: [
                        'section_present' => true,
                        'source_type' => 'takeoff_landing_report',
                        'report_reference' => 'TLR-30 SEQ-48273190 25MAY26 0115Z',
                        'airport' => 'KLAX',
                        'planned_runway' => '25R',
                        'outside_air_temperature_celsius' => 18.0,
                        'wind' => '250M08',
                        'qnh_inches_mercury' => 29.92,
                        'qnh_hectopascals' => null,
                        'maximum_runway_takeoff_weight' => ['amount' => 768000, 'unit' => 'lb'],
                        'flap_setting' => '15',
                        'anti_ice' => false,
                        'v1_knots' => 151,
                        'rotate_knots' => 158,
                        'v2_knots' => 164,
                        'planned_takeoff_weight' => ['amount' => 612400, 'unit' => 'lb'],
                        'maximum_field_takeoff_weight' => ['amount' => 766000, 'unit' => 'lb'],
                        'source_warnings' => ['32-41-03 - SOURCE BRAKE MESSAGE'],
                    ],
                ));
        });
        $component = Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'));

        $flightPlanKey = $component->get('flightPlanKey');

        $component
            ->call('selectTask', FlightPlanTask::Envelope->value)
            ->assertSet('activeTask', FlightPlanTask::Envelope->value)
            ->assertSeeHtml('wire:key="flight-plan-task-panel-envelope"')
            ->assertSeeText('Flight details')
            ->assertSeeText('109546')
            ->assertSeeText('CKS256')
            ->assertSeeText('B777-200F')
            ->assertSeeText('N774CK')
            ->assertSeeText('MORGAN A')
            ->assertSeeText('PIC')
            ->assertDontSeeText('4387')
            ->assertSeeText('RIVERA D')
            ->assertSeeText('SIC')
            ->assertSeeHtml('aria-label="Crew role SIC/FO"')
            ->assertDontSeeText('72914')
            ->assertSeeText('FOSTER B')
            ->assertSeeText('MCCULLOUGH M')
            ->assertSeeText('IRP')
            ->assertSeeText('BENNETT B')
            ->assertSeeText('MX')
            ->assertSeeText('GARCIA T')
            ->assertSeeText('LM')
            ->assertSeeText('Envelope source fragments remain private')
            ->assertDontSeeText('Confirmed provenance')
            ->assertDontSeeText('Takeoff and Landing Report')
            ->assertDontSeeText('TLR-30 SEQ-48273190 25MAY26 0115Z')
            ->assertDontSeeText('Source section')
            ->assertDontSeeText('Report reference')
            ->assertDontSeeText('Source inputs')
            ->assertDontSeeText('Assumptions')
            ->assertDontSeeText('Planned runway')
            ->assertDontSeeText('Outside air temperature')
            ->assertDontSeeText('Wind (source code)')
            ->assertDontSeeText('QNH')
            ->assertDontSeeText('Flap')
            ->assertDontSeeText('Anti-ice')
            ->assertDontSeeText('Source limits')
            ->assertDontSeeText('Permitted envelope')
            ->assertDontSeeText('Maximum runway takeoff weight')
            ->assertDontSeeText('Maximum field takeoff weight')
            ->assertDontSeeText('Source-calculated values')
            ->assertDontSeeText('Calculated result')
            ->assertDontSeeText('Planned takeoff weight')
            ->assertDontSeeText('Warnings')
            ->assertDontSeeText('612,400 LB')
            ->assertDontSeeText('768,000 LB')
            ->assertDontSeeText('151 kt')
            ->assertDontSeeText('32-41-03 - SOURCE BRAKE MESSAGE')
            ->assertDontSeeText('Source remarks')
            ->assertDontSeeText('No supported source warnings were listed')
            ->assertDontSeeText('No independent performance determination')
            ->assertDontSeeText('This view repeats the confirmed source result');

        $component
            ->call('$refresh')
            ->assertSet('activeTask', FlightPlanTask::Envelope->value)
            ->assertSeeText('MORGAN A')
            ->assertDontSeeText('612,400 LB');

        $this->assertSame($flightPlanKey, $component->get('flightPlanKey'));
    }

    public function test_envelope_remains_available_without_tlr_data(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan());
        });
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->call('selectTask', FlightPlanTask::Envelope->value)
            ->assertSet('activeTask', FlightPlanTask::Envelope->value)
            ->assertSeeHtml('wire:key="flight-plan-task-panel-envelope"')
            ->assertSeeText('Flight details')
            ->assertSeeText('PANC')
            ->assertSeeText('KMIA')
            ->assertDontSeeText('Not supported yet')
            ->assertDontSeeText('Not present in this release');
    }
}
