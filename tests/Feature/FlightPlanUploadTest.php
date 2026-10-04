<?php

namespace Tests\Feature;

use App\Livewire\FlightPlanBrief;
use App\Models\ExtractRequest;
use App\Models\User;
use App\Services\Clients\AirportLookupClient;
use App\Validation\FlightPlanValidationRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

class FlightPlanUploadTest extends TestCase
{
    use RefreshDatabase;

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

    private function uploadUrl(): string
    {
        return URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), absolute: false);
    }
}
