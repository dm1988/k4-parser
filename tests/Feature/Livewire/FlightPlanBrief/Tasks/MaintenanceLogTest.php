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

class MaintenanceLogTest extends FlightPlanBriefTestCase
{
    public function test_maintenance_log_renders_source_backed_context_items_limitations_and_crew_without_reparsing(): void
    {
        Storage::fake('user_flight_releases');
        $user = User::factory()->admin()->create();

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan(
                    identity: [
                        'flight_number' => 'CKS241',
                        'trip_number' => '109546',
                        'recall_number' => '62930',
                        'aircraft_type' => 'B777-200F',
                        'tail_number' => 'N774CK',
                        'flight_date' => '2026-05-25',
                        'release_revision' => null,
                    ],
                    fuel: [
                        'ramp' => ['amount' => 216800.0, 'unit' => 'lb'],
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
                        ['name' => 'RIVERA D', 'role' => 'SIC/FO', 'base' => null, 'employee_number' => '72914'],
                        ['name' => 'FOSTER B', 'role' => 'IRP', 'base' => null, 'employee_number' => '73521'],
                        ['name' => 'MCCULLOUGH M', 'role' => 'IRP', 'base' => null, 'employee_number' => '73642'],
                        ['name' => 'BENNETT B', 'role' => 'MX', 'base' => null, 'employee_number' => '5826'],
                        ['name' => 'GARCIA T', 'role' => 'LM', 'base' => null, 'employee_number' => '1957'],
                    ],
                    maintenance: [
                        'section_present' => true,
                        'items' => [
                            [
                                'type' => 'MEL',
                                'number' => '28-22-01',
                                'description' => 'Center tank override pump inoperative.',
                                'reference' => '1042',
                                'status' => 'OPEN',
                                'limitations' => null,
                                'procedures' => null,
                            ],
                            [
                                'type' => 'CDL',
                                'number' => '52-10-02',
                                'description' => 'Forward cargo door fairing segment missing.',
                                'reference' => null,
                                'status' => 'DEFERRED',
                                'limitations' => 'Source-listed operational limitation.',
                                'procedures' => 'Source-listed operations procedure.',
                            ],
                            [
                                'type' => 'DMI',
                                'number' => 'DMI-2099',
                                'description' => 'Source-listed inspection item.',
                                'reference' => null,
                                'status' => null,
                                'limitations' => null,
                                'procedures' => null,
                            ],
                            [
                                'type' => 'NEF',
                                'number' => '25-20-1-NEF-16',
                                'description' => 'Miscellaneous interior trim panel deferred.',
                                'reference' => '100224958',
                                'status' => null,
                                'limitations' => null,
                                'procedures' => null,
                            ],
                        ],
                    ],
                    etops: [
                        'section_present' => true,
                        'applicability' => 'confirmed_etops',
                    ],
                ));
        });
        $component = Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'));

        $flightPlanKey = $component->get('flightPlanKey');

        $component
            ->assertSeeHtmlInOrder([
                'wire:key="flight-plan-task-nav-overview"',
                'wire:key="flight-plan-task-nav-review_mel_cdl"',
                'wire:key="flight-plan-task-nav-jepp_pd_pro"',
            ])
            ->assertSeeHtml('aria-label="Review MEL / CDL: 4 items"')
            ->assertSeeHtml('bg-red-100 text-red-900 dark:bg-red-400/15 dark:text-red-200')
            ->assertSeeHtml('wire:key="flight-plan-overview-card-review_mel_cdl"')
            ->assertSeeHtml('aria-label="2 Active MEL/CDL Items"')
            ->assertSeeHtml('border-red-500/30 border-l-4 border-l-red-500 bg-red-500/5 backdrop-blur')
            ->assertSeeHtml('font-mono text-5xl font-black leading-none text-[#0B0E14] dark:text-slate-100')
            ->assertSeeText('Active MEL/CDL restrictions')
            ->assertDontSeeHtml('inline-flex w-fit rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold')
            ->assertSeeText('Review MEL / CDL Details')
            ->assertSeeHtml('aria-label="Review MEL / CDL Details"')
            ->call('selectTask', FlightPlanTask::MaintenanceLog->value)
            ->assertSet('activeTask', FlightPlanTask::MaintenanceLog->value)
            ->assertSeeHtml('wire:key="flight-plan-task-panel-maintenance_log"')
            ->assertSeeText('Flight details')
            ->assertSeeText('May 25, 2026')
            ->assertSeeText('05 25 26')
            ->assertSeeText('B777-200F')
            ->assertSeeText('N774CK')
            ->assertSeeText('109546')
            ->assertSeeText('PANC')
            ->assertSeeText('KMIA')
            ->assertSeeText('ETOPS flight')
            ->assertSeeText('Yes')
            ->assertSeeText('Estimated ramp fuel (1,000 LB)')
            ->assertSeeText('216.8')
            ->assertSeeInOrder([
                'MO DY YR',
                'Aircraft type',
                'Aircraft number',
                'Trip number',
            ])
            ->assertSeeText('4 source-listed items')
            ->assertSeeText('1 MEL · 1 CDL · 1 DMI · 1 NEF')
            ->assertSeeText('1 OPEN · 1 DEFERRED')
            ->assertSeeText('28-22-01')
            ->assertSeeText('1042')
            ->assertSeeHtml('data-copy-target="maintenance-item-number-1"')
            ->assertSeeHtml('data-copy-label="MEL 28-22-01 number"')
            ->assertSeeHtml('data-copy-target="maintenance-item-number-2"')
            ->assertSeeHtml('data-copy-label="CDL 52-10-02 number"')
            ->assertSeeText('DMI-2099')
            ->assertDontSeeHtml('data-copy-target="maintenance-item-number-3"')
            ->assertDontSeeHtml('data-copy-label="DMI DMI-2099 number"')
            ->assertSeeText('25-20-1-NEF-16')
            ->assertSeeHtml('data-copy-target="maintenance-item-number-4"')
            ->assertSeeHtml('data-copy-label="NEF 25-20-1-NEF-16 number"')
            ->assertSeeHtml('bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100')
            ->assertSeeHtml('title="Non-Essential Equipment &amp; Furnishings — NEF items are strictly cosmetic')
            ->assertSeeText('Forward cargo door fairing segment missing.')
            ->assertSeeText('Source-listed operational limitation.')
            ->assertSeeText('Source-listed operations procedure.')
            ->assertSeeText('MORGAN A')
            ->assertSeeText('PIC')
            ->assertSeeText('4387')
            ->assertSeeText('RIVERA D')
            ->assertSeeText('SIC')
            ->assertSeeHtml('aria-label="Crew role SIC/FO"')
            ->assertSeeText('72914')
            ->assertSeeText('FOSTER B')
            ->assertSeeText('MCCULLOUGH M')
            ->assertSeeText('IRP')
            ->assertSeeText('BENNETT B')
            ->assertSeeText('MX')
            ->assertSeeText('GARCIA T')
            ->assertSeeText('LM')
            ->assertSeeText('MEL / CDL')
            ->assertSeeInOrder([
                'Crew list',
                'Items',
                'Source-listed items',
                '28-22-01',
            ])
            ->assertDontSeeText('Source summary')
            ->assertSeeText('No airworthiness determination')
            ->assertSeeText('does not determine dispatchability')
            ->assertDontSeeText('Approved for dispatch');

        $component
            ->call('$refresh')
            ->assertSet('activeTask', FlightPlanTask::MaintenanceLog->value)
            ->assertSeeText('28-22-01');

        $component
            ->call('selectTask', FlightPlanTask::ReviewMelCdl->value)
            ->assertSet('activeTask', FlightPlanTask::ReviewMelCdl->value)
            ->assertSeeHtml('wire:key="flight-plan-task-panel-review_mel_cdl"')
            ->assertSeeHtml('aria-label="Review MEL / CDL: 4 items"')
            ->assertDontSeeHtml('bg-emerald-500 dark:bg-emerald-400')
            ->assertSeeText('4 source-listed items')
            ->assertSeeText('28-22-01')
            ->assertSeeText('52-10-02')
            ->assertSeeText('DMI-2099')
            ->assertSeeText('25-20-1-NEF-16')
            ->assertSeeHtml('data-copy-target="review-maintenance-item-1"')
            ->assertSeeHtml('data-copy-target="review-maintenance-item-2"')
            ->assertDontSeeHtml('data-copy-target="review-maintenance-item-3"')
            ->assertSeeHtml('data-copy-target="review-maintenance-item-4"')
            ->assertSeeText('Source-listed operational limitation.')
            ->assertSeeText('Source-listed operations procedure.')
            ->assertSeeText('No airworthiness determination')
            ->assertSeeText('does not determine dispatchability')
            ->assertSeeText('remain private to this extraction result');

        $this->assertSame($flightPlanKey, $component->get('flightPlanKey'));
    }

    public function test_an_explicit_empty_maintenance_section_is_available_and_reports_no_items(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan(
                    maintenance: [
                        'section_present' => true,
                        'items' => [],
                    ],
                    etops: [
                        'section_present' => true,
                        'applicability' => 'confirmed_non_etops',
                    ],
                ));
        });
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSeeHtmlInOrder([
                'wire:key="flight-plan-task-nav-weight_and_balance"',
                'wire:key="flight-plan-task-nav-review_mel_cdl"',
            ])
            ->assertSeeHtml('aria-label="Review MEL / CDL: 0 items"')
            ->assertSeeHtml('rounded-full bg-emerald-100 px-1.5')
            ->assertDontSeeHtml('bg-emerald-500 dark:bg-emerald-400')
            ->assertSeeText('No active MEL/CDL restrictions')
            ->assertSeeHtml('text-emerald-600 dark:text-emerald-400')
            ->assertDontSeeText('Review MEL / CDL Details')
            ->call('selectTask', FlightPlanTask::MaintenanceLog->value)
            ->assertSeeText('No maintenance items listed')
            ->assertSeeText('0 source-listed items')
            ->assertSeeText('ETOPS flight')
            ->assertSeeText('No')
            ->assertDontSeeText('Maintenance Log data was not found');
    }

    public function test_maintenance_log_exposes_shared_context_when_the_item_section_is_absent(): void
    {
        Storage::fake('user_flight_releases');

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
                    fuel: [
                        'ramp' => ['amount' => 216800.0, 'unit' => 'lb'],
                        'taxi' => null,
                        'takeoff' => null,
                        'trip' => null,
                        'contingency' => null,
                        'alternate' => null,
                        'final_reserve' => null,
                        'estimated_landing' => null,
                    ],
                    crewMembers: [[
                        'name' => 'Alex Morgan',
                        'role' => 'CP',
                        'base' => 'YIP',
                    ]],
                    maintenance: [
                        'section_present' => false,
                        'items' => [],
                    ],
                    etops: [
                        'section_present' => true,
                        'applicability' => 'confirmed_etops',
                    ],
                ));
        });
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->call('selectTask', FlightPlanTask::MaintenanceLog->value)
            ->assertSeeText('Flight details')
            ->assertSeeText('May 25, 2026')
            ->assertSeeText('B777-200F')
            ->assertSeeText('N774CK')
            ->assertSeeText('109546')
            ->assertSeeText('PANC')
            ->assertSeeText('KMIA')
            ->assertSeeText('ETOPS flight')
            ->assertSeeText('Yes')
            ->assertSeeText('Estimated ramp fuel (1,000 LB)')
            ->assertSeeText('216.8')
            ->assertSeeText('Alex Morgan')
            ->assertSeeText('No maintenance section found')
            ->assertDontSeeText('Maintenance Log data was not found')
            ->assertDontSeeText('No maintenance items listed');
    }
}
