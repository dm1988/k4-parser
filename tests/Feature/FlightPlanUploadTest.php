<?php

namespace Tests\Feature;

use App\Livewire\FlightPlanBrief;
use App\Models\ExtractRequest;
use App\Models\User;
use App\Services\Clients\AirportLookupClient;
use App\Services\FlightPlan\Extractor\FlightPlanTextExtractor;
use App\Services\Infrastructure\FlightPlanResultStore;
use App\Validation\FlightPlanValidationRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FlightPlanUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_labels_are_removed_before_extracting_and_saving_flight_plan_fields(): void
    {
        Storage::fake('user_flight_releases');
        Http::preventStrayRequests();
        $owner = User::factory()->admin()->create();
        $source = "KALITTA AIR TRIP 109001 KALITTA BRIEF PAGE 1 OF 83 PAGE 1 OF 83 RECALL 12345 N772CK B77L 10/31/26\n"
            ."(FPL-CKS242-IS-B77L/H-SDE2-RJAA0900-N0500F360 DCT PIGOK-VHHH0412 VMMC-DOF/261031 REG/N772CK)\n"
            ."FUEL BURN /1000 LBS\nTTL RMP KALITTA BRIEF PAGE 3 OF 83 PAGE 3 OF 83 84.5\n"
            .file_get_contents(base_path('tests/Fixtures/FlightPlan/waypoints/primary-header-page-break.txt'))
            ."\nDEPARTURE: NARITA\nMETAR RJAA 310800Z 31003KT CAVOK\n"
            ."KALITTA BRIEF PAGE 11 OF 83 PAGE 11 OF 83\nUNRELATED NOTAM TEXT";
        $textExtractor = $this->createMock(FlightPlanTextExtractor::class);
        $textExtractor->expects($this->once())->method('extract')->willReturn($source);
        $this->app->instance(FlightPlanTextExtractor::class, $textExtractor);
        $airportLookup = $this->createMock(AirportLookupClient::class);
        $airportLookup->method('resolveByIcaos')->willReturn([]);
        $this->app->instance(AirportLookupClient::class, $airportLookup);

        $component = Livewire::actingAs($owner)->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('release.pdf', 10, 'application/pdf'))
            ->assertHasNoErrors();

        $saved = app(FlightPlanResultStore::class)->get($owner, $component->get('flightPlanKey'));
        $data = $saved['flight_plan_data'];
        $this->assertSame('109001', $data['identity']['tripNumber']);
        $this->assertSame('12345', $data['identity']['recallNumber']);
        $this->assertSame(['amount' => 84500, 'unit' => 'lb'], $data['fuelPlan']['ramp']);
        $this->assertSame(['35464N', 'ARIES', 'TOC', 'VHHH'], array_column($data['waypoints'], 'identifier'));
        $this->assertSame(['METAR RJAA 310800Z 31003KT CAVOK'], $data['weather']['departure']['metars']);
        $this->assertStringNotContainsString('KALITTA BRIEF PAGE', json_encode($data, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('UNRELATED NOTAM TEXT', json_encode($data['weather'], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('missingFiles')]
    public function test_missing_files_return_a_recoverable_422_without_storing_anything(array $payload): void
    {
        Storage::fake('tmp-for-tests');
        Log::spy();

        $response = $this->post($this->uploadUrl(), $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonFragment(['files.0' => ['The upload could not be completed. Please select the file and try again.']])
            ->assertHeader('X-Upload-Request-Id');

        Storage::disk('tmp-for-tests')->assertDirectoryEmpty('/');
        Log::shouldHaveReceived('log')->once()->withArgs(
            fn (string $level, string $message, array $context): bool => $level === 'warning'
                && $message === 'Temporary upload request.'
                && $context['status'] === 422
                && $context['file_count'] === 0
                && $context['returned_path_count'] === 0
                && $context['request_id'] === $response->headers->get('X-Upload-Request-Id')
                && $context['php_sapi'] === PHP_SAPI
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'private.pdf'),
        );
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function missingFiles(): array
    {
        return [
            'absent' => [[]],
            'empty' => [['files' => []]],
            'text instead of files' => [['files' => 'private.pdf']],
            'text array instead of files' => [['files' => ['private.pdf']]],
        ];
    }

    public function test_a_sparse_file_collection_is_rejected_before_storage(): void
    {
        Storage::fake('tmp-for-tests');

        $this->post($this->uploadUrl(), [
            'files' => [1 => UploadedFile::fake()->create('release.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');

        Storage::disk('tmp-for-tests')->assertDirectoryEmpty('/');
    }

    public function test_multiple_uploads_remain_supported_for_other_components(): void
    {
        Storage::fake('tmp-for-tests');

        $this->post($this->uploadUrl(), ['files' => [
            UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('second.pdf', 10, 'application/pdf'),
        ]], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonCount(2, 'paths');
    }

    public function test_successful_uploads_are_not_logged_when_diagnostics_are_disabled(): void
    {
        Storage::fake('tmp-for-tests');
        Log::spy();
        config()->set('livewire.upload_diagnostics', false);

        $this->post($this->uploadUrl(), ['files' => [
            UploadedFile::fake()->create('release.pdf', 10, 'application/pdf'),
        ]], ['Accept' => 'application/json'])->assertOk();

        Log::shouldNotHaveReceived('log');
    }

    public function test_upload_diagnostics_correlate_paths_without_logging_private_upload_data(): void
    {
        Storage::fake('tmp-for-tests');
        Log::spy();
        config()->set('livewire.upload_diagnostics', true);

        $response = $this->post($this->uploadUrl(), ['files' => [
            UploadedFile::fake()->create('private-flight-release.pdf', 10, 'application/pdf'),
        ]], ['Accept' => 'application/json', 'X-CSRF-TOKEN' => 'private-token'])
            ->assertOk();
        $reference = $response->json('paths.0');

        Log::shouldHaveReceived('log')->once()->withArgs(function (string $level, string $message, array $context) use ($reference): bool {
            $logged = json_encode($context, JSON_THROW_ON_ERROR);

            return $level === 'info' && $message === 'Temporary upload request.'
                && $context['file_count'] === 1 && $context['returned_path_count'] === 1
                && $context['status'] === 200
                && $context['reference_hash'] === hash_hmac('sha256', $reference, (string) config('app.key'))
                && ! str_contains($logged, $reference)
                && ! str_contains($logged, 'private-flight-release.pdf')
                && ! str_contains($logged, 'private-token')
                && ! str_contains($logged, 'signature=');
        });
    }

    public function test_php_upload_errors_are_rejected_and_logged_as_codes(): void
    {
        Storage::fake('tmp-for-tests');
        Log::spy();
        config()->set('livewire.upload_diagnostics', true);

        $this->post($this->uploadUrl(), ['files' => [
            new UploadedFile('', 'private.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true),
        ]], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');

        Storage::disk('tmp-for-tests')->assertDirectoryEmpty('/');
        Log::shouldHaveReceived('log')->once()->withArgs(
            fn (string $level, string $message, array $context): bool => $context['status'] === 422
                && $context['php_upload_errors'] === [UPLOAD_ERR_INI_SIZE],
        );
    }

    public function test_nested_file_entries_are_rejected_without_breaking_diagnostics(): void
    {
        Storage::fake('tmp-for-tests');
        Log::spy();
        config()->set('livewire.upload_diagnostics', true);

        $this->post($this->uploadUrl(), ['files' => [
            ['nested' => UploadedFile::fake()->create('release.pdf', 10, 'application/pdf')],
        ]], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');

        Storage::disk('tmp-for-tests')->assertDirectoryEmpty('/');
        Log::shouldHaveReceived('log')->withArgs(
            fn (string $level, string $message, array $context): bool => $context['status'] === 422
                && $context['php_upload_errors'] === [null],
        )->once();
    }

    public function test_temporary_upload_accepts_a_pdf_at_the_flight_plan_size_limit(): void
    {
        Storage::fake('tmp-for-tests');

        $file = UploadedFile::fake()->create('flight-release.pdf', 25600, 'application/pdf');

        $this->post($this->uploadUrl(), ['files' => [$file]], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonCount(1, 'paths');

        $this->assertTrue(Validator::make(['flightRelease' => $file], FlightPlanValidationRules::rules())->passes());
    }

    public function test_oversized_upload_returns_json_validation_errors_instead_of_a_redirect(): void
    {
        $file = UploadedFile::fake()->create('oversized.pdf', 25601, 'application/pdf');

        $this->post($this->uploadUrl(), [
            'files' => [$file],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');

        $validator = Validator::make(['flightRelease' => $file], FlightPlanValidationRules::rules(), FlightPlanValidationRules::messages());
        $this->assertTrue($validator->fails());
        $this->assertSame('The PDF is too large. The maximum allowed size is 25 MB.', $validator->errors()->first('flightRelease'));
    }

    public function test_an_invalid_upload_signature_returns_a_json_error(): void
    {
        $this->post(route('livewire.upload-file'), [], ['Accept' => 'application/json'])
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_upload_validation_errors_are_displayed_on_the_component(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->call('_uploadErrored', 'flightRelease', json_encode([
                'errors' => ['files.0' => ['The PDF is too large. The maximum allowed size is 25 MB.']],
            ], JSON_THROW_ON_ERROR), false)
            ->assertHasErrors('flightRelease')
            ->assertSee('The PDF is too large. The maximum allowed size is 25 MB.');
    }

    public function test_the_supplied_large_release_finishes_processing(): void
    {
        $path = storage_path('app/private/flight_releases/CKS020016KCVG (1).pdf');

        if (! is_file($path)) {
            $this->markTestSkipped('The private large flight-release fixture is not available.');
        }

        Storage::fake('user_flight_releases');
        Http::preventStrayRequests();
        $airportLookup = $this->createMock(AirportLookupClient::class);
        $airportLookup->method('lookupByIcao')->willReturn(null);
        $this->app->instance(AirportLookupClient::class, $airportLookup);

        $streamedOutput = '';
        ob_start(function (string $output) use (&$streamedOutput): string {
            $streamedOutput .= $output;

            return '';
        });

        try {
            Livewire::actingAs(User::factory()->admin()->create())
                ->test(FlightPlanBrief::class)
                ->set('flightRelease', UploadedFile::fake()->createWithContent('flight-release.pdf', file_get_contents($path)))
                ->assertHasNoErrors()
                ->assertSet('flightRelease', null)
                ->assertSet('extractionJustCompleted', true)
                ->assertDispatched('scroll-to-release-summary')
                ->assertDontSeeText('Flight plan brief ready. Upload and extraction completed successfully.')
                ->assertSeeHtml('wire:key="flight-plan-brief-results"')
                ->assertSee('KCVG');
        } finally {
            ob_end_clean();
        }

        $messages = array_map(
            static fn (string $frame): string => json_decode($frame, true, flags: JSON_THROW_ON_ERROR)['body']['content'],
            explode("\n", str_replace('}{"stream":', "}\n{\"stream\":", $streamedOutput)),
        );
        $this->assertSame('Upload successful', $messages[0]);
        $this->assertLessThan(35, count($messages));
        $this->assertContains('Reading PDF…', $messages);
        $this->assertContains('Extracting text — page 1 of 219…', $messages);
        $this->assertContains('Extracting text — page 219 of 219…', $messages);
        $this->assertSame([
            'Extracting flight details…',
            'Building your flight plan brief…',
            'Saving your brief…',
        ], array_slice($messages, -3));
        $this->assertStringNotContainsString('Extracting text from images', $streamedOutput);

        $this->assertSame('success', ExtractRequest::query()->sole()->status);
        Storage::disk('user_flight_releases')->assertDirectoryEmpty('/');
    }

    public function test_the_supplied_release_with_a_page_break_in_the_flight_plan_finishes_processing(): void
    {
        $path = storage_path('app/private/flight_releases/CKS020426RKSI.pdf');

        if (! is_file($path)) {
            $this->markTestSkipped('The private flight-release fixture is not available.');
        }

        Storage::fake('user_flight_releases');
        Http::preventStrayRequests();
        $airportLookup = $this->createMock(AirportLookupClient::class);
        $airportLookup->method('lookupByIcao')->willReturn(null);
        $this->app->instance(AirportLookupClient::class, $airportLookup);

        ob_start(static fn (string $output): string => '');

        try {
            Livewire::actingAs(User::factory()->admin()->create())
                ->test(FlightPlanBrief::class)
                ->set('flightRelease', UploadedFile::fake()->createWithContent('flight-release.pdf', file_get_contents($path)))
                ->assertHasNoErrors()
                ->assertSet('flightRelease', null)
                ->assertSet('extractionJustCompleted', true)
                ->assertSee('RKSI')
                ->assertSee('VHHH');
        } finally {
            ob_end_clean();
        }

        $this->assertSame('success', ExtractRequest::query()->sole()->status);
        Storage::disk('user_flight_releases')->assertDirectoryEmpty('/');
    }

    private function uploadUrl(): string
    {
        return URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), absolute: false);
    }
}
