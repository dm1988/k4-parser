<?php

namespace Tests\Feature\Livewire\FlightPlanBrief\Lifecycle;

use App\Actions\ShouldPromptForCoffee;
use App\DTOs\ParsedFlightPlanData;
use App\Enums\FlightPlanTask;
use App\Exceptions\FlightRouteNotFoundException;
use App\Livewire\FlightPlanBrief;
use App\Models\ExtractRequest;
use App\Models\User;
use App\Services\FlightPlan\Extractor\ExtractFlightPlanData;
use App\Services\Infrastructure\ExtractRequestLogger;
use App\Services\Infrastructure\FlightPlanResultStore;
use App\View\Models\FlightPlanPageData;
use App\View\Models\FlightReleasePageViewModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Feature\Livewire\FlightPlanBrief\FlightPlanBriefTestCase;

class ExtractionTest extends FlightPlanBriefTestCase
{
    public function test_a_successful_extraction_renders_results_without_a_redirect_and_can_reset(): void
    {
        Storage::fake('user_flight_releases');
        $user = User::factory()->admin()->create();
        $privateEvidence = 'PRIVATE-EVIDENCE-MUST-NOT-ESCAPE';
        $privateStoragePath = '/private/flight-releases/source-document.pdf';

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock) use ($privateEvidence, $privateStoragePath): void {
            $this->expectOnce($mock, 'extractFile')
                ->withArgs(fn (string $path): bool => str_contains($path, 'framework/testing/disks/user_flight_releases'))
                ->andReturn($this->parsedFlightPlan([
                    ...$this->flightPlan(),
                    'sensitive_internal_marker' => 'must-not-reach-livewire',
                ], sourceFragments: [
                    'raw_page_text' => $privateEvidence,
                    'storage_path' => $privateStoragePath,
                ]));
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
            ->assertDispatched('offline-fuel-release-changed', function (string $event, array $params) use ($user): bool {
                return $params['ownerId'] === (string) $user->getKey()
                    && $params['flightPlanKey'] === app(FlightPlanResultStore::class)->latest($user)?->result_key;
            })
            ->assertSet('extractionJustCompleted', true)
            ->assertDontSeeText('Flight plan brief ready. Upload and extraction completed successfully.')
            ->assertSeeHtml('wire:key="flight-plan-brief-results"')
            ->assertSeeHtml('id="release-summary"')
            ->assertDontSeeText('Flight release PDF')
            ->assertSeeText('Extract another flight plan')
            ->assertSeeHtml('aria-label="Release summary"')
            ->assertSeeHtml('overflow-hidden rounded-xl border border-[#1B365D]/10 bg-[#F8F9FA] shadow-sm dark:border-slate-700 dark:bg-slate-800')
            ->assertSeeHtml('gap-4 p-4 transition-all duration-300 sm:p-5 lg:flex-row lg:items-center lg:justify-between lg:gap-8')
            ->assertSeeHtml('lg:min-w-[280px] lg:shrink-0')
            ->assertSeeHtml('truncate font-mono text-[2rem] font-medium leading-none tracking-tighter')
            ->assertSeeHtml('flex shrink-0 flex-col justify-center gap-px leading-tight')
            ->assertSeeHtml('max-w-xl flex-1 items-center gap-3 rounded-lg bg-slate-50 p-2 backdrop-blur-sm dark:bg-slate-800/80 sm:gap-5 lg:bg-transparent lg:p-0')
            ->assertSeeTextInOrder(['Flight not present', 'Aircraft not present', 'Tail not present', 'PANC', 'Date not present', 'Time not present', '07h12m', 'KMIA'])
            ->assertSeeText('Operational support status')
            ->assertDontSeeHtml('aria-label="Available"')
            ->assertDontSeeText('Available')
            ->assertSeeHtml('aria-label="Not present"')
            ->assertDontSeeHtml('aria-label="Not supported"')
            ->assertSeeHtml('h-2.5 w-2.5 ring-1 ring-inset ring-black/10 dark:ring-white/10')
            ->assertDontSeeHtml('title="OpSpec B44 Authorized"')
            ->assertDontSeeHtml('wire:key="flight-plan-task-nav-fuel_score-b44"')
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

        $frames = array_map(
            static fn (string $frame): array => json_decode($frame, true, flags: JSON_THROW_ON_ERROR),
            explode("\n", str_replace('}{"stream":', "}\n{\"stream\":", $this->streamedOutput)),
        );
        $this->assertSame([
            'Upload successful',
            'Preparing your flight plan…',
            'Building your flight plan brief…',
            'Saving your brief…',
        ], array_column(array_column($frames, 'body'), 'content'));
        $this->assertSame([
            'flight-plan-upload-status',
            'flight-plan-progress',
            'flight-plan-progress',
            'flight-plan-progress',
        ], array_column(array_column($frames, 'body'), 'name'));
        $this->assertSame('', ob_get_contents());

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
        $this->assertEqualsCanonicalizing(['flightRelease', 'flightPlanKey', 'activeTask', 'extractionJustCompleted', 'usesTaskRoutes'], array_keys($snapshotData));
        $serializedSnapshot = json_encode($snapshotData, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('must-not-reach-livewire', $serializedSnapshot);
        $this->assertStringNotContainsString($privateEvidence, $serializedSnapshot);
        $this->assertStringNotContainsString($privateStoragePath, $serializedSnapshot);

        $storedFlightPlan = app(FlightPlanResultStore::class)->get($user, $flightPlanKey);

        $this->assertIsArray($storedFlightPlan);
        $this->assertSame(['flight_plan_data'], array_keys($storedFlightPlan));
        $this->assertArrayNotHasKey('sensitive_internal_marker', $storedFlightPlan);
        $this->assertSame('PANC', $storedFlightPlan['flight_plan_data']['route']['departure']);
        $this->assertArrayNotHasKey('sourceFragments', $storedFlightPlan['flight_plan_data']);
        $serializedResult = json_encode($storedFlightPlan, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($privateEvidence, $serializedResult);
        $this->assertStringNotContainsString($privateStoragePath, $serializedResult);
        $this->assertStringNotContainsString($privateEvidence, $component->html());
        $this->assertStringNotContainsString($privateStoragePath, $component->html());
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
            ->assertSet('extractionJustCompleted', false)
            ->assertDontSeeText('Flight plan brief ready.')
            ->assertSeeText('Drop your flight plan here')
            ->assertDontSeeText('Extracted flight plan');

        $this->assertFalse($component->viewData('isResultsView'));
        $this->assertNull(app(FlightPlanResultStore::class)->get($user, $flightPlanKey));

        Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->assertSet('flightPlanKey', null)
            ->assertSeeText('Drop your flight plan here');
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
                    $this->assertSame(1, $extractRequest->uploaded_file_count);

                    return $this->parsedFlightPlan([...$this->flightPlan(), 'route' => 'DCT TEST']);
                });
        });
        Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', $file)
            ->assertHasNoErrors()
            ->assertSeeText('Operational support status');

        $extractRequest = ExtractRequest::query()->sole();

        $this->assertSame('success', $extractRequest->status);
        $this->assertSame(1, $extractRequest->uploaded_file_count);
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
                    && $context['mime_type'] === 'application/pdf'
                    && $context['size'] > 0
                    && $context['error_code'] === FlightRouteNotFoundException::class
                    && ! array_key_exists('filename', $context)
                    && ! array_key_exists('message', $context);
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
            ->assertSet('extractionJustCompleted', false)
            ->assertDontSeeText('Flight plan brief ready.')
            ->assertSee('A flight plan block was found, but the route segment could not be identified');

        $this->assertStringContainsString('Upload successful', $this->streamedOutput);
        $this->assertStringContainsString('Preparing your flight plan', $this->streamedOutput);
        $this->assertStringNotContainsString('Saving your brief', $this->streamedOutput);
        $this->assertSame('', ob_get_contents());

        $extractRequest = ExtractRequest::query()->sole();
        $this->assertSame('failed', $extractRequest->status);
        $this->assertSame(1, $extractRequest->uploaded_file_count);
        $this->assertSame(class_basename(FlightRouteNotFoundException::class), $extractRequest->error_code);
        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());
    }

    public function test_unexpected_extraction_exception_is_reported_and_shown_as_a_recoverable_error(): void
    {
        Storage::fake('user_flight_releases');
        Exceptions::fake();
        $log = Log::spy();
        $privateFailure = 'PRIVATE RAW PAGE /private/flight-release.pdf';
        $extractionException = new RuntimeException($privateFailure);

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock) use ($extractionException): void {
            $this->expectOnce($mock, 'extractFile')
                ->andThrow($extractionException);
        });

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertNoRedirect()
            ->assertSet('flightRelease', null)
            ->assertSet('flightPlanKey', null)
            ->assertHasErrors(['flightRelease'])
            ->assertSeeText('We could not process that flight release. Please try again.')
            ->assertSet('extractionJustCompleted', false)
            ->assertDontSeeText('Flight plan brief ready.')
            ->assertSeeHtml('wire:loading.remove.flex')
            ->assertSeeHtml('class="flex flex-col items-center gap-2"')
            ->assertDontSeeText($privateFailure);

        Exceptions::assertReported(
            fn (RuntimeException $exception): bool => $exception->getMessage() === 'Flight plan extraction failed.'
                && $exception->getPrevious() === $extractionException,
        );
        $this->assertReceivedOnce($log, 'error')->withArgs(
            fn (string $message, array $context): bool => $message === 'K4 extraction failed'
                && $context['error_code'] === RuntimeException::class
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), $privateFailure),
        );
        $this->assertStringNotContainsString($privateFailure, json_encode($component->errors()->toArray(), JSON_THROW_ON_ERROR));

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
            fn (RuntimeException $exception): bool => $exception->getMessage() === 'Flight plan extraction failed.',
        );

        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());
        $this->assertSame(0, ExtractRequest::query()->count());
    }
}
