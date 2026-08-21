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
            ->assertSeeHtml('wire:target="extractFlightPlan"')
            ->assertSeeText('Uploading PDF…')
            ->assertSeeText('Processing flight plan…')
            ->assertSeeText('Extract route')
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
            ->call('extractFlightPlan')
            ->assertHasErrors(['flightRelease' => 'mimes'])
            ->assertSee('Only PDF flight release uploads are supported.');

        $component
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertHasNoErrors('flightRelease');

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
            ->call('extractFlightPlan')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSet('flightRelease', null)
            ->assertSeeHtml('wire:key="flight-plan-brief-results"')
            ->assertDontSeeText('Flight release PDF')
            ->assertSeeText('Extract another flight plan')
            ->assertSeeText('Operational support status')
            ->assertSeeHtml('wire:key="flight-plan-overview-card-flight_init"')
            ->assertSeeHtml('aria-label="Available"')
            ->assertSeeHtml('aria-label="Not present"')
            ->assertSeeHtml('aria-label="Not supported"')
            ->assertSeeHtml('h-2.5 w-2.5 ring-1 ring-inset ring-black/10 dark:ring-white/10')
            ->assertDispatched('open-modal', name: 'buy-me-a-coffee')
            ->call('selectTask', FlightPlanTask::Fms->value)
            ->assertSeeText('Extracted flight plan')
            ->assertSeeText('PANC')
            ->assertSeeText('KMIA')
            ->assertSeeText('KRSW')
            ->assertSeeText('Miami International Airport')
            ->assertSeeText('Southwest Florida International Airport')
            ->assertSeeText('Departure runway')
            ->assertSeeText('SUMMR2 SCTRR')
            ->assertSeeText('ETOPS critical points')
            ->assertSeeText('EENT coordinates')
            ->assertSeeText('EEXP coordinates')
            ->assertSee('data-copy-target="etp-0-airport-0"', escape: false)
            ->assertSee('grid divide-y divide-[#1B365D]/6 dark:divide-slate-700 md:grid-cols-3 md:divide-x md:divide-y-0', escape: false)
            ->assertSee('break-words font-mono text-xs leading-relaxed', escape: false)
            ->assertSee('DCT Q139', escape: false)
            ->assertSee(' TEST', escape: false);

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
            ->assertSeeText('Extracted flight plan')
            ->assertSeeText('DCT Q139');

        $this->assertTrue($component->viewData('isResultsView'));

        $component
            ->call('extractAnotherFlightPlan')
            ->assertSet('flightRelease', null)
            ->assertSet('flightPlanKey', null)
            ->assertSeeText('Flight release PDF')
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
            ->call('extractFlightPlan')
            ->assertSet('activeTask', FlightPlanTask::Overview->value)
            ->assertSeeInOrder(array_map(
                static fn (FlightPlanTask $task): string => $task->label(),
                FlightPlanTask::cases(),
            ))
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
            ->assertSeeText('Extracted flight plan')
            ->assertSeeText('DCT Q139 TEST');

        $this->assertSame($flightPlanKey, $component->get('flightPlanKey'));
    }

    public function test_jepp_pd_pro_preserves_the_current_fms_task_presentation_in_its_own_panel(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan());
        });
        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'formatForIcaoDisplay')
                ->with('DCT Q139 TEST')
                ->andReturn("DCT Q139\n TEST");
        });

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->call('extractFlightPlan');

        $flightPlanKey = $component->get('flightPlanKey');

        foreach ([FlightPlanTask::JeppPdPro, FlightPlanTask::Fms] as $task) {
            $component
                ->call('selectTask', $task->value)
                ->assertSet('activeTask', $task->value)
                ->assertSeeHtml('wire:key="flight-plan-task-panel-'.$task->value.'"')
                ->assertSeeText('Extracted flight plan')
                ->assertSeeText('PANC')
                ->assertSeeText('KMIA')
                ->assertSeeText('KRSW')
                ->assertSeeText('Departure runway')
                ->assertSeeText('25R')
                ->assertSeeText('SUMMR2 SCTRR')
                ->assertSeeText('Arrival runway')
                ->assertSeeText('33R')
                ->assertSeeText('GUKDO GUKD2E')
                ->assertSeeText('ETOPS critical points')
                ->assertSee('value="KSFO"', escape: false)
                ->assertSee('value="PACD"', escape: false)
                ->assertSee('value="N45 43.7 W143 53.1"', escape: false)
                ->assertSeeText('EENT coordinates')
                ->assertSeeText('EEXP coordinates')
                ->assertSee('data-copy-target="flight-route-output"', escape: false)
                ->assertDontSee('data-copy-label="Departure runway"', escape: false)
                ->assertDontSee('data-copy-label="Arrival runway"', escape: false)
                ->assertDontSee('data-copy-label="SID"', escape: false)
                ->assertDontSee('data-copy-label="STAR"', escape: false)
                ->assertSee('DCT Q139', escape: false)
                ->assertSee(' TEST', escape: false);
        }

        $this->assertSame($flightPlanKey, $component->get('flightPlanKey'));
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
                        'slot_times_utc' => ['2026-05-25T18:45:00Z'],
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
            ->call('extractFlightPlan')
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
            ->assertSeeText('FL 330')
            ->assertSeeText('4,000 NM')
            ->assertSeeText('120,000 LB')
            ->assertSeeText('1 approved UTC slot')
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
            ->call('extractFlightPlan')
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
            ->call('extractFlightPlan')
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
            ->call('extractFlightPlan')
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
            ->call('extractFlightPlan')
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
            ->call('extractFlightPlan')
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
            ->call('extractFlightPlan')
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
            ->call('extractFlightPlan')
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
            legacy: $legacy,
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
