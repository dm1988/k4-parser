<?php

namespace Tests\Feature\Livewire;

use App\Actions\ShouldPromptForCoffee;
use App\DTOs\AirportData;
use App\DTOs\ParsedFlightPlanData;
use App\Enums\FlightPlanTask;
use App\Exceptions\FlightRouteNotFoundException;
use App\Livewire\FlightPlanBrief;
use App\Models\ExtractRequest;
use App\Models\User;
use App\Services\FlightPlan\Extractor\ExtractFlightPlanData;
use App\Services\FlightPlan\Extractor\FlightRouteExtractor;
use App\Services\Infrastructure\ExtractRequestLogger;
use App\Services\Infrastructure\FlightPlanResultCache;
use App\View\Models\FlightPlanPageData;
use App\View\Models\FlightReleasePageViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use LogicException;
use Mockery\CompositeExpectation;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class FlightPlanBriefTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_starts_on_the_upload_view_with_accessible_loading_states(): void
    {
        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->assertSet('flightRelease', null)
            ->assertSet('flightPlanKey', null)
            ->assertSeeHtml('wire:key="flight-plan-brief-upload"')
            ->assertSeeHtml('wire:target="flightRelease"')
            ->assertDontSeeHtml('wire:submit="extractFlightPlan"')
            ->assertDontSeeHtml('wire:target="extractFlightPlan"')
            ->assertSeeText('Drop your flight plan here')
            ->assertSeeText('Upload one PDF flight plan. Click to browse your files.')
            ->assertSeeHtml('class="absolute inset-0 h-full w-full cursor-pointer opacity-0"')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:loading.flex')
            ->assertSeeHtml('min-h-48')
            ->assertSeeText('Processing flight plan…')
            ->assertSeeText('Please wait while your PDF is uploaded and parsed.')
            ->assertDontSeeText('Extract route')
            ->assertDontSeeText('Extracted flight plan');

        $this->assertFalse($component->viewData('isResultsView'));
    }

    public function test_the_derived_view_state_is_not_serialized_to_the_client(): void
    {
        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class);

        $this->assertArrayNotHasKey('view', $component->getData());
        $this->assertArrayNotHasKey('isResultsView', $component->getData());
        $this->assertFalse($component->viewData('isResultsView'));
    }

    public function test_the_result_key_cannot_be_changed_by_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->expectExceptionMessage('Cannot update locked property: [flightPlanKey]');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightPlanKey', '01JTESTRESULTKEYABC1234567');
    }

    public function test_the_active_task_cannot_be_changed_directly_by_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->expectExceptionMessage('Cannot update locked property: [activeTask]');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('activeTask', FlightPlanTask::Fms->value);
    }

    public function test_component_actions_enforce_authentication_verification_feature_and_gate_access(): void
    {
        Livewire::test(FlightPlanBrief::class)
            ->call('extractFlightPlan')
            ->assertUnauthorized();

        Livewire::actingAs(User::factory()->unverified()->create())
            ->test(FlightPlanBrief::class)
            ->call('extractFlightPlan')
            ->assertForbidden();

        Config::set('features.flight_release.enabled', false);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->call('extractFlightPlan')
            ->assertNotFound();

        Config::set('features.flight_release.enabled', true);
        Config::set('features.flight_release.for_all_users', false);

        Livewire::actingAs(User::factory()->create())
            ->test(FlightPlanBrief::class)
            ->call('extractAnotherFlightPlan')
            ->assertForbidden();
    }

    public function test_it_validates_pdf_uploads_and_clears_the_error_when_the_file_changes(): void
    {
        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->call('extractFlightPlan')
            ->assertHasErrors(['flightRelease' => 'required'])
            ->assertSee('Upload a flight release PDF to extract the route.')
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.txt', 8, 'text/plain'))
            ->assertHasErrors(['flightRelease' => 'mimes'])
            ->assertSee('Only PDF flight release uploads are supported.');

        $this->assertSame(0, ExtractRequest::query()->count());
    }

    public function test_a_successful_extraction_renders_results_without_a_redirect_and_can_reset(): void
    {
        Storage::fake('user_flight_releases');
        $user = User::factory()->admin()->create();

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->withArgs(fn (string $path): bool => str_contains($path, 'framework/testing/disks/user_flight_releases'))
                ->andReturn($this->parsedFlightPlan([
                    ...$this->flightPlan(),
                    'sensitive_internal_marker' => 'must-not-reach-livewire',
                ]));
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn("DCT Q139\n TEST");
        });
        $this->mock(ShouldPromptForCoffee::class, function (MockInterface $mock) use ($user): void {
            $this->expectOnce($mock, 'handle')
                ->withArgs(fn (User $candidate): bool => $candidate->is($user))
                ->andReturn(true);
        });

        $component = Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSet('flightRelease', null)
            ->assertDispatched('scroll-to-release-summary')
            ->assertSeeHtml('wire:key="flight-plan-brief-results"')
            ->assertSeeHtml('id="release-summary"')
            ->assertDontSeeText('Flight release PDF')
            ->assertSeeText('Extract another flight plan')
            ->assertSeeHtml('aria-label="Release summary"')
            ->assertSeeHtml('overflow-hidden rounded-xl border border-[#1B365D]/10 bg-[#F8F9FA] shadow-sm dark:border-slate-700 dark:bg-slate-800')
            ->assertSeeHtml('gap-4 p-4 transition-all duration-300 sm:p-5 lg:flex-row lg:items-center lg:justify-between lg:gap-8')
            ->assertSeeHtml('lg:min-w-[280px] lg:shrink-0')
            ->assertSeeHtml('max-w-xl flex-1 items-center gap-3 rounded-lg bg-slate-50 p-2 backdrop-blur-sm dark:bg-slate-800/80 sm:gap-5 lg:bg-transparent lg:p-0')
            ->assertSeeTextInOrder(['Flight not present', 'Aircraft not present', 'Tail not present', 'PANC', 'Date not present', 'Time not present', '07h12m', 'KMIA'])
            ->assertSeeText('Operational support status')
            ->assertSeeHtml('wire:key="flight-plan-overview-card-flight_init"')
            ->assertDontSeeHtml('aria-label="Available"')
            ->assertDontSeeText('Available')
            ->assertSeeHtml('aria-label="Not present"')
            ->assertDontSeeHtml('aria-label="Not supported"')
            ->assertSeeHtml('h-2.5 w-2.5 ring-1 ring-inset ring-black/10 dark:ring-white/10')
            ->assertDispatched('open-modal', name: 'buy-me-a-coffee')
            ->call('selectTask', FlightPlanTask::Fms->value)
            ->assertSeeText('FMS route setup')
            ->assertSeeText('PANC')
            ->assertSeeText('KMIA')
            ->assertSeeText('KRSW')
            ->assertSeeText('Miami International Airport')
            ->assertSeeText('Southwest Florida International Airport')
            ->assertSeeText('Departure runway')
            ->assertSeeText('SUMMR2 SCTRR')
            ->assertDontSeeText('ETOPS critical points')
            ->assertDontSee('data-copy-target=', escape: false)
            ->assertSee('grid divide-y divide-[#1B365D]/6 dark:divide-slate-700 md:grid-cols-3 md:divide-x md:divide-y-0', escape: false)
            ->assertSee('break-words font-mono text-xs leading-relaxed', escape: false)
            ->assertSeeTextInOrder(['DCT', 'Q139', 'TEST']);

        $this->assertTrue($component->viewData('isResultsView'));
        $viewModel = $component->viewData('model');
        $this->assertInstanceOf(FlightReleasePageViewModel::class, $viewModel);
        $this->assertInstanceOf(FlightPlanPageData::class, $viewModel->pageData);
        $this->assertSame('PANC', $viewModel->pageData->flightPlan->route->departure->value);

        $snapshotData = $component->getData();
        $flightPlanKey = $component->get('flightPlanKey');

        $this->assertArrayNotHasKey('flightPlan', $snapshotData);
        $this->assertArrayHasKey('flightPlanKey', $snapshotData);
        $this->assertIsString($flightPlanKey);
        $this->assertStringNotContainsString('must-not-reach-livewire', json_encode($snapshotData, JSON_THROW_ON_ERROR));

        $cachedFlightPlan = app(FlightPlanResultCache::class)->get($user, $flightPlanKey);

        $this->assertIsArray($cachedFlightPlan);
        $this->assertArrayNotHasKey('sensitive_internal_marker', $cachedFlightPlan);
        $this->assertSame('PANC', $cachedFlightPlan['flight_plan_data']['route']['departure']);
        $this->assertArrayNotHasKey('sourceFragments', $cachedFlightPlan['flight_plan_data']);
        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());

        $component
            ->call('$refresh')
            ->assertSeeText('FMS route setup')
            ->assertSeeTextInOrder(['DCT', 'Q139', 'TEST'])
            ->assertDontSee('data-copy-target=', escape: false);

        $this->assertTrue($component->viewData('isResultsView'));

        $component
            ->call('extractAnotherFlightPlan')
            ->assertSet('flightRelease', null)
            ->assertSet('flightPlanKey', null)
            ->assertSeeText('Drop your flight plan here')
            ->assertDontSeeText('Extracted flight plan');

        $this->assertFalse($component->viewData('isResultsView'));
        $this->assertNull(app(FlightPlanResultCache::class)->get($user, $flightPlanKey));
    }

    public function test_the_task_workspace_is_responsive_accessible_and_rehydrates_without_reparsing(): void
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
                        'slot_times_utc' => [],
                    ],
                ));
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
        });

        $component = Livewire::actingAs($user)
            ->test(FlightPlanBrief::class);

        $component
            ->set('flightRelease', UploadedFile::fake()->create(
                'flight-release.pdf',
                120,
                'application/pdf',
            ))
            ->assertSet('activeTask', FlightPlanTask::Overview->value)
            ->assertSeeInOrder(array_map(
                static fn (FlightPlanTask $task): string => $task->label(),
                array_values(array_filter(
                    FlightPlanTask::cases(),
                    static fn (FlightPlanTask $task): bool => $task !== FlightPlanTask::ReviewMelCdl,
                )),
            ))
            ->assertSeeText(FlightPlanTask::ReviewMelCdl->label())
            ->assertSeeInOrder([
                'Task',
                FlightPlanTask::Overview->label(),
            ])
            ->assertSeeHtml('aria-labelledby="flight-plan-task-navigation-heading"')
            ->assertSeeHtml('id="flight-plan-task-navigation-heading"')
            ->assertSeeHtml('aria-current="page"')
            ->assertSeeHtml('focus-visible:ring-2')
            ->assertSeeHtml('overflow-x-auto')
            ->assertSeeHtml('lg:grid-cols-[15rem_minmax(0,1fr)]')
            ->assertSeeHtml('wire:key="flight-plan-task-panel-overview"')
            ->assertSeeText('CKS241')
            ->assertSeeText('May 25, 2026')
            ->assertSeeText('B777-200F')
            ->assertSeeText('Tail N774CK')
            ->assertSeeText('ETD (UTC)')
            ->assertSeeText('ETA (UTC)')
            ->assertSeeText('Release revision')
            ->assertSeeText('3');

        $flightPlanKey = $component->get('flightPlanKey');

        $component
            ->call('selectTask', FlightPlanTask::JeppPdPro->value)
            ->assertSet('activeTask', FlightPlanTask::JeppPdPro->value)
            ->assertSeeHtml('wire:key="flight-plan-task-panel-jepp_pd_pro"')
            ->assertSeeText('Extracted flight plan')
            ->assertSeeText('Departure runway')
            ->assertSeeText('ETOPS critical points')
            ->assertSeeText('DCT Q139 TEST')
            ->assertDontSeeText('Not supported yet')
            ->call('selectTask', FlightPlanTask::SlotTimes->value)
            ->assertSet('activeTask', FlightPlanTask::SlotTimes->value)
            ->assertSeeText('Not present in this release')
            ->assertSeeText('Slot Times data was not found')
            ->call('$refresh')
            ->assertSet('activeTask', FlightPlanTask::SlotTimes->value)
            ->call('selectTask', 'untrusted-task')
            ->assertSet('activeTask', FlightPlanTask::SlotTimes->value)
            ->call('selectTask', FlightPlanTask::Fms->value)
            ->assertSet('activeTask', FlightPlanTask::Fms->value)
            ->assertSeeText('FMS route setup')
            ->assertSeeTextInOrder(['DCT', 'Q139', 'TEST'])
            ->assertDontSee('data-copy-target=', escape: false);

        $this->assertSame($flightPlanKey, $component->get('flightPlanKey'));
    }

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
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn("DCT Q139\n TEST");
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
            ->assertSeeText('Planned Duration')
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

    public function test_etops_uses_a_dedicated_typed_layout_without_inferring_approval(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')->andReturn($this->parsedFlightPlan());
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
        });

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->call('selectTask', FlightPlanTask::Etops->value)
            ->assertSet('activeTask', FlightPlanTask::Etops->value)
            ->assertSeeHtml('wire:key="flight-plan-task-panel-etops"')
            ->assertSeeText('ETOPS source data')
            ->assertSeeText('Not confirmed')
            ->assertSeeText('Boundary points')
            ->assertSeeText('EENT')
            ->assertSee('value="N40 31.1 W131 22.6"', escape: false)
            ->assertSeeText('EEXP')
            ->assertSee('value="N45 19.3 E151 36.4"', escape: false)
            ->assertSeeText('Equal-time points')
            ->assertSeeText('ETP1')
            ->assertSee('value="N45 43.7 W143 53.1"', escape: false)
            ->assertSeeText('ETOPS alternates')
            ->assertSeeText('KSFO')
            ->assertSeeText('PACD')
            ->assertSeeText('Source scenarios')
            ->assertSeeText('ALL ENGINE/DECOMPRESSION/LRC')
            ->assertSeeText('No approval or suitability determination')
            ->assertDontSeeText('Its dedicated operational layout is scheduled in the next focused task.');

        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());
    }

    public function test_weather_renders_all_raw_airport_reports_and_raim_without_interpretation(): void
    {
        Storage::fake('user_flight_releases');
        $weather = [
            'departure' => [
                'airport' => 'PANC',
                'metars' => [
                    'METAR PANC 250553Z 22006KT 10SM FEW060 14/06 A2991',
                    'SPECI PANC 250520Z 24008KT 8SM -RA BKN050 13/07 A2992',
                ],
                'tafs' => ["TAF PANC 250521Z 2506/2612 28006KT P6SM BKN070\nFM250800 21005KT P6SM SCT080"],
            ],
            'destination' => [
                'airport' => 'KMIA',
                'metars' => ['METAR KMIA 250553Z 00000KT 10SM SCT250 25/22 A3003'],
                'tafs' => ['TAF KMIA 250539Z 2506/2612 VRB05KT P6SM FEW030 SCT250'],
            ],
            'alternate' => [
                'airport' => 'KRSW',
                'metars' => ['METAR KRSW 250553Z AUTO 11003KT 10SM CLR 23/22 A3005'],
                'tafs' => ['TAF KRSW 250521Z 2506/2606 VRB03KT P6SM SCT030 BKN250'],
            ],
            'raim' => 'PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM 1020Z TO 1240Z',
        ];

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock) use ($weather): void {
            $this->expectOnce($mock, 'extractFile')->andReturn($this->parsedFlightPlan(weather: $weather));
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
        });

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->call('selectTask', FlightPlanTask::Weather->value)
            ->assertSet('activeTask', FlightPlanTask::Weather->value)
            ->assertSeeHtml('wire:key="flight-plan-task-panel-weather"')
            ->assertSeeText('Airport weather')
            ->assertSeeTextInOrder(['Departure', 'PANC', 'Destination', 'KMIA', 'Alternate', 'KRSW'])
            ->assertSeeText('METAR PANC 250553Z 22006KT 10SM FEW060 14/06 A2991')
            ->assertSeeText('SPECI PANC 250520Z 24008KT 8SM -RA BKN050 13/07 A2992')
            ->assertSeeText('TAF PANC 250521Z 2506/2612 28006KT P6SM BKN070')
            ->assertSeeHtml("TAF PANC 250521Z 2506/2612 28006KT P6SM BKN070\nFM250800 21005KT P6SM SCT080")
            ->assertSeeText('METAR KMIA 250553Z 00000KT 10SM SCT250 25/22 A3003')
            ->assertSeeText('TAF KMIA 250539Z 2506/2612 VRB05KT P6SM FEW030 SCT250')
            ->assertSeeText('METAR KRSW 250553Z AUTO 11003KT 10SM CLR 23/22 A3005')
            ->assertSeeText('TAF KRSW 250521Z 2506/2606 VRB03KT P6SM SCT030 BKN250')
            ->assertSeeText('PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM 1020Z TO 1240Z')
            ->assertSeeText('Raw reports only')
            ->assertDontSeeText('Its dedicated operational layout is scheduled in the next focused task.');
    }

    public function test_fms_renders_honest_missing_states_without_copy_controls(): void
    {
        Storage::fake('user_flight_releases');
        $legacy = [
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

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock) use ($legacy): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan(
                    legacy: $legacy,
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
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
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
                        ['name' => 'MORGAN A', 'role' => 'PIC', 'base' => null],
                        ['name' => 'RIVERA D', 'role' => 'SIC/FO', 'base' => null],
                        ['name' => 'FOSTER B', 'role' => 'IRP', 'base' => null],
                        ['name' => 'MCCULLOUGH M', 'role' => 'IRP', 'base' => null],
                        ['name' => 'BENNETT B', 'role' => 'MX', 'base' => null],
                        ['name' => 'GARCIA T', 'role' => 'LM', 'base' => null],
                    ],
                    maintenance: [
                        'section_present' => true,
                        'etops_applicability' => 'confirmed_etops',
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
                ));
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
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
            ->assertSeeText('RIVERA D')
            ->assertSeeText('SIC/FO')
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
                        'etops_applicability' => 'confirmed_non_etops',
                        'items' => [],
                    ],
                ));
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
        });

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSeeHtmlInOrder([
                'wire:key="flight-plan-task-nav-weight_and_balance"',
                'wire:key="flight-plan-task-nav-review_mel_cdl"',
            ])
            ->assertSeeHtml('aria-label="Review MEL / CDL: 0 items"')
            ->call('selectTask', FlightPlanTask::MaintenanceLog->value)
            ->assertSeeText('No maintenance items listed')
            ->assertSeeText('0 source-listed items')
            ->assertSeeText('ETOPS flight')
            ->assertSeeText('No')
            ->assertDontSeeText('Maintenance Log data was not found');
    }

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
                        'block_duration' => null,
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
                        ['name' => 'GONZALEZ D', 'role' => 'SIC/FO', 'base' => null, 'employee_number' => '72914'],
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
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
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
            ->assertSeeText('Tail number')
            ->assertSeeText('N770CK')
            ->assertSeeTextInOrder(['PANC', 'May 25, 2026', '1355z'])
            ->assertSeeHtml('<span class="text-[10px]">z</span>')
            ->assertSeeHtml('max-w-xl')
            ->assertSeeText('ETD (UTC)')
            ->assertSeeHtml('id="flight-init-etd"')
            ->assertSeeText('1355Z')
            ->assertSeeText('Estimated ramp fuel')
            ->assertSeeText('225,500 LB')
            ->assertSeeText('Flight number')
            ->assertSeeText('CKS256')
            ->assertSeeText('PANC')
            ->assertSeeText('KMIA')
            ->assertSeeText('ACARS INIT DATE')
            ->assertDontSeeText('Filed initial altitude')
            ->assertDontSeeText('FMS initial altitude')
            ->assertSeeHtml('id="flight-init-acars-init-date"')
            ->assertSeeText('11')
            ->assertSeeText('not derived from the release flight date')
            ->assertSeeText('MORGAN A')
            ->assertSeeText('4387')
            ->assertSeeText('GONZALEZ D')
            ->assertSeeText('72914')
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
                        ['name' => 'MORGAN A', 'role' => 'PIC', 'base' => null],
                        ['name' => 'RIVERA D', 'role' => 'SIC/FO', 'base' => null],
                        ['name' => 'FOSTER B', 'role' => 'IRP', 'base' => null],
                        ['name' => 'MCCULLOUGH M', 'role' => 'IRP', 'base' => null],
                        ['name' => 'BENNETT B', 'role' => 'MX', 'base' => null],
                        ['name' => 'GARCIA T', 'role' => 'LM', 'base' => null],
                    ],
                    envelope: [
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
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
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
            ->assertSeeText('RIVERA D')
            ->assertSeeText('SIC/FO')
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

    public function test_envelope_is_not_supported_when_a_tlr_section_has_no_supported_result(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan(envelope: [
                    'section_present' => true,
                    'source_type' => 'takeoff_landing_report',
                    'planned_takeoff_weight' => null,
                ]));
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
        });

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->call('selectTask', FlightPlanTask::Envelope->value)
            ->assertSeeText('Not supported yet')
            ->assertSeeText('Envelope requires confirmed fixtures and typed extraction')
            ->assertDontSeeText('Not present in this release');
    }

    public function test_weight_and_balance_renders_planned_values_independent_statuses_and_server_derived_ramp_weight(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')->andReturn($this->parsedFlightPlan(
                fuel: [
                    'ramp' => ['amount' => 225500.0, 'unit' => 'lb'],
                    'taxi' => null,
                    'takeoff' => ['amount' => 223489.0, 'unit' => 'lb'],
                    'trip' => null,
                    'contingency' => null,
                    'alternate' => null,
                    'final_reserve' => null,
                    'estimated_landing' => null,
                ],
                weightBalance: [
                    'basic_operating_weight' => ['amount' => 335858, 'unit' => 'lb', 'status' => 'confirmed'],
                    'planned_payload' => ['amount' => null, 'unit' => 'lb', 'status' => 'conflict'],
                    'planned_zero_fuel_weight' => ['amount' => 353858, 'unit' => 'lb', 'status' => 'confirmed'],
                    'planned_takeoff_gross_weight' => ['amount' => 577347, 'unit' => 'lb', 'status' => 'confirmed'],
                    'planned_estimated_landing_weight' => ['amount' => 371893, 'unit' => 'lb', 'status' => 'confirmed'],
                ],
            ));
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
        });

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->call('selectTask', FlightPlanTask::WeightAndBalance->value)
            ->assertSet('activeTask', FlightPlanTask::WeightAndBalance->value)
            ->assertSeeText('Planned source values')
            ->assertSeeText('Base & Payload')
            ->assertSeeText('Departure')
            ->assertSeeText('Arrival')
            ->assertSeeText('Basic operating weight')
            ->assertSeeText('335,858')
            ->assertSeeText('Payload')
            ->assertSeeText('Conflict')
            ->assertSeeText('Zero-fuel weight')
            ->assertSeeText('Takeoff fuel')
            ->assertSeeText('223,489')
            ->assertSeeText('Ramp weight')
            ->assertSeeText('579,358')
            ->assertSeeText('Takeoff gross weight')
            ->assertSeeText('Estimated landing weight')
            ->assertSeeText('LB')
            ->assertSeeText('Derived server-side from confirmed zero-fuel weight and ramp fuel.')
            ->assertDontSeeText('Permitted limit')
            ->assertDontSeeText('Limit unavailable')
            ->assertDontSeeText('Confirmed');
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
                        'etops_applicability' => 'confirmed_etops',
                        'items' => [],
                    ],
                ));
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
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
                        'taxi' => null,
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
                ));
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
        });

        $component = Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSet('activeTask', FlightPlanTask::Overview->value)
            ->assertSeeText('Flight and aircraft')
            ->assertSeeText('CKS241')
            ->assertSeeText('May 25, 2026')
            ->assertSeeText('B777-200F')
            ->assertSeeText('N774CK')
            ->assertSeeText('PANC')
            ->assertSeeText('KMIA')
            ->assertSeeText('KRSW')
            ->assertSeeText('May 25, 2026 · 1830Z')
            ->assertSeeText('May 26, 2026 · 0215Z')
            ->assertSeeText('Initial altitude')
            ->assertSeeText('FL330')
            ->assertSeeText('4,000 NM')
            ->assertSeeText('120,000 LB')
            ->assertSeeText('2 approved UTC slots')
            ->assertSeeText('1 critical point · EENT · EEXP')
            ->assertSeeText('Operational support status')
            ->assertSeeText('GENDEC')
            ->assertSeeText('Flight plan filing')
            ->assertSeeText('Weather / RAIM')
            ->assertSeeText('Maintenance')
            ->assertDontSeeText('On plan')
            ->assertDontSeeText('Dispatchable');

        $flightPlanKey = $component->get('flightPlanKey');
        $detailTasks = [
            FlightPlanTask::FlightInit,
            FlightPlanTask::Fms,
            FlightPlanTask::SlotTimes,
            FlightPlanTask::FuelScore,
            FlightPlanTask::Etops,
        ];

        foreach ($detailTasks as $task) {
            $component
                ->call('selectTask', FlightPlanTask::Overview->value)
                ->assertSeeHtml('wire:key="flight-plan-overview-card-'.$task->value.'"')
                ->assertSeeHtml('aria-label="Open '.$task->label().' task"')
                ->call('selectTask', $task->value)
                ->assertSet('activeTask', $task->value);
        }

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
            ->assertSeeText('Off time (UTC)')
            ->assertSeeText('Planned ETA')
            ->assertSeeText('Remaining fuel')
            ->assertSee('147.7 k lbs')
            ->assertSeeText('does not alter extracted release data')
            ->assertSee('FIX01')
            ->assertDontSeeText('Coordinate')
            ->assertDontSeeText('Its dedicated operational layout is scheduled in the next focused task.');

        $this->assertSame($flightPlanKey, $component->get('flightPlanKey'));
    }

    public function test_overview_labels_sparse_values_without_inventing_zero_or_supported_statuses(): void
    {
        Storage::fake('user_flight_releases');

        $legacy = [
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

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock) use ($legacy): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan($legacy));
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
        });

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSet('activeTask', FlightPlanTask::Overview->value)
            ->assertSeeText('Not present in this release')
            ->assertSeeText('No alternate airport listed.')
            ->assertSeeText('GENDEC')
            ->assertSeeText('Weather / RAIM')
            ->assertDontSeeText('0 LB')
            ->assertDontSeeText('0 KG')
            ->assertDontSeeText('On plan')
            ->assertDontSeeText('Dispatchable');

        $this->assertGreaterThanOrEqual(
            9,
            substr_count($component->html(), 'Not present in this release'),
        );

        $component
            ->call('selectTask', FlightPlanTask::Etops->value)
            ->assertSet('activeTask', FlightPlanTask::Etops->value)
            ->assertSeeText('Not present in this release')
            ->assertDontSeeText('ETOPS source data');
    }

    public function test_a_missing_cached_result_derives_the_upload_view_without_mutating_component_state(): void
    {
        Storage::fake('user_flight_releases');
        $user = User::factory()->admin()->create();

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan());
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn('DCT Q139 TEST');
        });
        $this->mock(ShouldPromptForCoffee::class, function (MockInterface $mock) use ($user): void {
            $this->expectOnce($mock, 'handle')
                ->withArgs(fn (User $candidate): bool => $candidate->is($user))
                ->andReturn(false);
        });

        $component = Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSeeText('Operational support status');

        $flightPlanKey = $component->get('flightPlanKey');
        $this->assertIsString($flightPlanKey);

        app(FlightPlanResultCache::class)->forget($user, $flightPlanKey);

        $component
            ->call('$refresh')
            ->assertSet('flightPlanKey', $flightPlanKey)
            ->assertSeeText('Flight release PDF')
            ->assertDontSeeText('Extracted flight plan');

        $this->assertFalse($component->viewData('isResultsView'));
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
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT TEST')
                ->andReturn('DCT TEST');
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

    public function test_successful_extraction_records_request_metadata_and_explicit_counts(): void
    {
        Storage::fake('user_flight_releases');
        Config::set('features.flight_release.for_all_users', true);
        Config::set('app.version', '1.2.3');
        Config::set('app.extractor_version', '2026.08');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf');

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock) use ($user): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturnUsing(function () use ($user): ParsedFlightPlanData {
                    $extractRequest = ExtractRequest::query()->sole();

                    $this->assertSame($user->getKey(), $extractRequest->user_id);
                    $this->assertSame('pdf', $extractRequest->source_type);
                    $this->assertSame('flight_plan', $extractRequest->parser_type);
                    $this->assertSame('partial', $extractRequest->status);

                    return $this->parsedFlightPlan([...$this->flightPlan(), 'route' => 'DCT TEST']);
                });
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT TEST')
                ->andReturn('DCT TEST');
        });

        Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', $file)
            ->assertHasNoErrors()
            ->assertSeeText('Operational support status');

        $extractRequest = ExtractRequest::query()->sole();

        $this->assertSame('success', $extractRequest->status);
        $this->assertNull($extractRequest->error_code);
        $this->assertSame(1, $extractRequest->detected_event_count);
        $this->assertSame(1, $extractRequest->detected_flight_count);
        $this->assertSame(0, $extractRequest->detected_hotel_count);
        $this->assertSame('1.2.3', $extractRequest->app_version);
        $this->assertSame('2026.08', $extractRequest->extractor_version);
        $this->assertNotEmpty($extractRequest->file_hash);
        $this->assertSame($file->getSize(), $extractRequest->file_size_bytes);
        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());
    }

    public function test_route_not_found_stays_on_upload_records_failure_and_logs_context(): void
    {
        Storage::fake('user_flight_releases');
        Log::shouldReceive('error')->once();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Flight release route extraction failed'
                    && $context['filename'] === 'flight-release.pdf'
                    && $context['mime_type'] === 'application/pdf'
                    && $context['size'] > 0
                    && str_contains($context['message'], 'route segment could not be identified');
            });

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andThrow(FlightRouteNotFoundException::routeSegmentMissing());
        });

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertNoRedirect()
            ->assertSet('flightRelease', null)
            ->assertSet('flightPlanKey', null)
            ->assertHasErrors(['flightRelease'])
            ->assertSee('A flight plan block was found, but the route segment could not be identified');

        $extractRequest = ExtractRequest::query()->sole();
        $this->assertSame('failed', $extractRequest->status);
        $this->assertSame(class_basename(FlightRouteNotFoundException::class), $extractRequest->error_code);
        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());
    }

    public function test_unexpected_extraction_exception_is_reported_and_shown_as_a_recoverable_error(): void
    {
        Storage::fake('user_flight_releases');
        Exceptions::fake();

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andThrow(new RuntimeException('Unexpected extractor failure'));
        });

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertNoRedirect()
            ->assertSet('flightRelease', null)
            ->assertSet('flightPlanKey', null)
            ->assertHasErrors(['flightRelease'])
            ->assertSeeText('We could not process that flight release. Please try again.');

        Exceptions::assertReported(
            fn (RuntimeException $exception): bool => $exception->getMessage() === 'Unexpected extractor failure',
        );

        $extractRequest = ExtractRequest::query()->sole();
        $this->assertSame('failed', $extractRequest->status);
        $this->assertSame(RuntimeException::class, $extractRequest->error_code);
        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());
    }

    public function test_extract_request_logging_exception_is_reported_and_shown_as_a_recoverable_error(): void
    {
        Storage::fake('user_flight_releases');
        Exceptions::fake();

        $this->mock(ExtractRequestLogger::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'start')
                ->andThrow(new RuntimeException('Unable to record extraction'));
            $mock->shouldNotReceive('error');
        });
        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('extractFile');
        });

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertNoRedirect()
            ->assertSet('flightRelease', null)
            ->assertSet('flightPlanKey', null)
            ->assertHasErrors(['flightRelease'])
            ->assertSeeText('We could not process that flight release. Please try again.');

        Exceptions::assertReported(
            fn (RuntimeException $exception): bool => $exception->getMessage() === 'Unable to record extraction',
        );

        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());
        $this->assertSame(0, ExtractRequest::query()->count());
    }

    /** @return array<string, mixed> */
    private function flightPlan(): array
    {
        return [
            'departure' => 'PANC',
            'destination' => 'KMIA',
            'alternate' => 'KRSW',
            'departure_airport' => new AirportData('PANC', 'ANC', 'Ted Stevens Anchorage International Airport', 'Anchorage', 'Alaska', 'United States'),
            'destination_airport' => new AirportData('KMIA', 'MIA', 'Miami International Airport', 'Miami', 'Florida', 'United States'),
            'alternate_airport' => new AirportData('KRSW', 'RSW', 'Southwest Florida International Airport', 'Fort Myers', 'Florida', 'United States'),
            'departure_runway' => '25R',
            'arrival_runway' => '33R',
            'departure_sid' => 'SUMMR2 SCTRR',
            'arrival_star' => 'GUKDO GUKD2E',
            'etps' => [[
                'label' => 'ETP1',
                'airports' => 'KSFO-PACD',
                'coordinates' => 'N45 43.7 W143 53.1',
                'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
            ]],
            'eent_coordinates' => 'N40 31.1 W131 22.6',
            'eexp_coordinates' => 'N45 19.3 E151 36.4',
            'initial_altitude' => 'FL 330',
            'duration' => '07h12m',
            'route' => 'DCT Q139 TEST',
        ];
    }

    /** @param array<string, mixed>|null $legacy */
    private function parsedFlightPlan(
        ?array $legacy = null,
        ?array $identity = null,
        ?array $schedule = null,
        ?array $route = null,
        ?array $fuel = null,
        ?array $crewMembers = null,
        ?array $maintenance = null,
        ?array $envelope = null,
        ?array $flightInit = null,
        ?array $waypoints = null,
        ?array $weather = null,
        ?array $weightBalance = null,
    ): ParsedFlightPlanData {
        $legacy ??= $this->flightPlan();

        $routeData = [
            'departure' => (string) $legacy['departure'],
            'destination' => (string) $legacy['destination'],
            'alternate' => is_string($legacy['alternate'] ?? null) ? $legacy['alternate'] : null,
            'route' => is_string($legacy['route'] ?? null) ? $legacy['route'] : null,
            'departure_runway' => is_string($legacy['departure_runway'] ?? null) ? $legacy['departure_runway'] : null,
            'arrival_runway' => is_string($legacy['arrival_runway'] ?? null) ? $legacy['arrival_runway'] : null,
            'departure_sid' => is_string($legacy['departure_sid'] ?? null) ? $legacy['departure_sid'] : null,
            'arrival_star' => is_string($legacy['arrival_star'] ?? null) ? $legacy['arrival_star'] : null,
            'distance_nautical_miles' => null,
        ];

        return new ParsedFlightPlanData(
            identity: $identity ?? [
                'flight_number' => null,
                'trip_number' => null,
                'recall_number' => null,
                'aircraft_type' => null,
                'tail_number' => null,
                'flight_date' => null,
                'release_revision' => null,
            ],
            schedule: $schedule ?? [
                'etd_utc' => null,
                'eta_utc' => null,
                'block_duration' => null,
                'report_time_utc' => null,
                'duty_end_utc' => null,
                'slot_times_utc' => [],
            ],
            route: [...$routeData, ...($route ?? [])],
            fuel: $fuel ?? array_fill_keys([
                'ramp', 'taxi', 'takeoff', 'trip', 'contingency', 'alternate', 'final_reserve', 'estimated_landing',
            ], null),
            crewMembers: $crewMembers ?? [],
            maintenance: $maintenance ?? [
                'section_present' => false,
                'etops_applicability' => 'unknown',
                'items' => [],
            ],
            envelope: $envelope ?? [],
            flightInit: $flightInit ?? [],
            etops: [
                'etps' => is_array($legacy['etps'] ?? null) ? $legacy['etps'] : [],
                'eent_coordinates' => is_string($legacy['eent_coordinates'] ?? null) ? $legacy['eent_coordinates'] : null,
                'eexp_coordinates' => is_string($legacy['eexp_coordinates'] ?? null) ? $legacy['eexp_coordinates'] : null,
            ],
            weather: $weather ?? [],
            weightBalance: $weightBalance ?? [],
            legacy: $legacy,
            waypoints: $waypoints ?? [],
        );
    }

    private function expectOnce(MockInterface $mock, string $method): CompositeExpectation
    {
        $expectation = $mock->shouldReceive($method);

        if (! $expectation instanceof CompositeExpectation) {
            throw new LogicException("Expected a composite Mockery expectation for [{$method}].");
        }

        return $expectation->once();
    }
}
