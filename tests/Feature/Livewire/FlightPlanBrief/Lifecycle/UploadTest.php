<?php

namespace Tests\Feature\Livewire\FlightPlanBrief\Lifecycle;

use App\Livewire\FlightPlanBrief;
use App\Models\ExtractRequest;
use App\Models\User;
use App\Services\FlightPlan\Extractor\ExtractFlightPlanData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Livewire\FlightPlanBrief\FlightPlanBriefTestCase;

class UploadTest extends FlightPlanBriefTestCase
{
    #[DataProvider('invalidCompletions')]
    public function test_invalid_completion_recovers_with_an_upload_error(mixed $paths, bool $multiple): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->call('_finishUpload', 'flightRelease', $paths, $multiple)
            ->assertHasErrors('flightRelease')
            ->assertDispatched('upload:errored', name: 'flightRelease')
            ->assertNotDispatched('upload:finished')
            ->assertSee('The upload could not be completed. Please select the file and try again.')
            ->assertSet('flightRelease', null)
            ->assertSet('flightPlanKey', null);

        $this->assertDatabaseCount('extract_requests', 0);
    }

    /** @return array<string, array{mixed, bool}> */
    public static function invalidCompletions(): array
    {
        return [
            'empty' => [[], false],
            'missing' => [null, false],
            'sparse' => [[1 => 'reference'], false],
            'invalid entry' => [[null], false],
            'blank entry' => [[''], false],
            'extra files' => [['first', 'second'], false],
            'multiple mode' => [['reference'], true],
        ];
    }

    public function test_failed_upload_can_retry_and_then_upload_another_pdf(): void
    {
        Storage::fake('tmp-for-tests');
        Storage::fake('user_flight_releases');
        $user = User::factory()->admin()->create();

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $mock->shouldReceive('extractFile')->twice()->andReturn($this->parsedFlightPlan());
        });

        $component = Livewire::actingAs($user)->test(FlightPlanBrief::class)
            ->call('_finishUpload', 'flightRelease', [], false)
            ->assertHasErrors('flightRelease')
            ->assertDispatched('upload:errored', name: 'flightRelease');

        $url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), absolute: false);
        $failedUpload = $this->post($url, [], ['Accept' => 'application/json'])->assertUnprocessable();
        $component->call('_uploadErrored', 'flightRelease', $failedUpload->getContent(), false)
            ->assertHasErrors('flightRelease')
            ->assertSee('The upload could not be completed. Please select the file and try again.');

        $invalidUpload = $this->post($url, ['files' => [
            UploadedFile::fake()->create('release.txt', 10, 'text/plain'),
        ]], ['Accept' => 'application/json'])->assertOk();
        $component->call('_finishUpload', 'flightRelease', $invalidUpload->json('paths'), false)
            ->assertHasErrors(['flightRelease' => 'mimes'])
            ->assertSee('Only PDF flight release uploads are supported.');

        $firstUpload = $this->post($url, ['files' => [
            UploadedFile::fake()->create('release.pdf', 120, 'application/pdf'),
        ]], ['Accept' => 'application/json'])->assertOk();

        $component->call('_finishUpload', 'flightRelease', $firstUpload->json('paths'), false)
            ->assertHasNoErrors()
            ->assertSet('extractionJustCompleted', true)
            ->assertSet('flightRelease', null)
            ->assertDispatched('upload:finished', name: 'flightRelease')
            ->assertSee('PANC');
        $firstKey = $component->get('flightPlanKey');

        $component->call('_finishUpload', 'flightRelease', [], false)
            ->assertHasErrors('flightRelease')
            ->assertSet('flightPlanKey', $firstKey);

        $component->call('extractAnotherFlightPlan')->assertSet('flightPlanKey', null);

        $secondUpload = $this->post($url, ['files' => [
            UploadedFile::fake()->create('release.pdf', 120, 'application/pdf'),
        ]], ['Accept' => 'application/json'])->assertOk();

        $component->call('_finishUpload', 'flightRelease', $secondUpload->json('paths'), false)
            ->assertHasNoErrors()
            ->assertSet('extractionJustCompleted', true)
            ->assertSet('flightRelease', null);

        $this->assertNotSame($firstKey, $component->get('flightPlanKey'));
        $this->assertDatabaseCount('extract_requests', 2);
        Storage::disk('user_flight_releases')->assertDirectoryEmpty('/');
    }

    public function test_lifecycle_diagnostics_identify_repeated_references_without_logging_them(): void
    {
        config()->set('livewire.upload_diagnostics', true);
        Log::spy();
        $reference = 'private-unsigned-reference.pdf';

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->call('_startUpload', 'flightRelease', [['name' => 'private.pdf', 'size' => 100, 'type' => 'application/pdf']], false)
            ->assertDispatched('upload:generatedSignedUrl', name: 'flightRelease');

        $replayedComponent = clone $component;
        $component->call('_finishUpload', 'flightRelease', [$reference], false)->assertForbidden();
        $replayedComponent->call('_finishUpload', 'flightRelease', [$reference], false)->assertForbidden();

        Log::shouldHaveReceived('log')->withArgs(function (string $level, string $message, array $context) use ($reference): bool {
            return $level === 'info' && $message === 'Flight release upload lifecycle.'
                && $context['phase'] === 'completion_received'
                && $context['file_count'] === 1
                && $context['has_temporary_file'] === false
                && $context['reference_hash'] === hash_hmac('sha256', $reference, (string) config('app.key'))
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), $reference);
        })->twice();
        Log::shouldHaveReceived('log')->withArgs(
            fn (string $level, string $message, array $context): bool => $context['phase'] === 'started'
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'private.pdf'),
        )->once();
    }

    public function test_it_starts_on_the_upload_view_with_accessible_loading_states(): void
    {
        config()->set('mail.admin_address', 'bugs@example.test');

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->assertSet('flightRelease', null)
            ->assertSet('flightPlanKey', null)
            ->assertSet('extractionJustCompleted', false)
            ->assertSeeText('Flight Plan Brief')
            ->assertDontSeeHtml('wire:click="clearResults"')
            ->assertDontSeeHtml('wire:click="extractAnotherFlightPlan"')
            ->assertSeeHtml('wire:key="flight-plan-brief-upload"')
            ->assertSeeHtml('wire:target="flightRelease"')
            ->assertDontSeeHtml('wire:submit="extractFlightPlan"')
            ->assertDontSeeHtml('wire:target="extractFlightPlan"')
            ->assertSeeText('Drop your flight plan here')
            ->assertSeeText('Upload one PDF flight plan. Click to browse your files.')
            ->assertSeeText('Maximum size: 25 MB.')
            ->assertSeeHtml('class="absolute inset-0 h-full w-full cursor-pointer opacity-0"')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:loading.remove.flex')
            ->assertSeeHtml('wire:loading.flex')
            ->assertSeeHtml('class="flex flex-col items-center gap-2"')
            ->assertSeeHtml('min-h-48')
            ->assertSeeText('Uploading flight plan…')
            ->assertSeeHtml('x-on:livewire-upload-progress="uploadProgress = $event.detail.progress"')
            ->assertSeeHtml('x-on:livewire-upload-error="uploadProgress = 0"')
            ->assertSeeHtml('x-on:livewire-upload-cancel="uploadProgress = 0"')
            ->assertSeeHtml('wire:stream.replace="flight-plan-upload-status"')
            ->assertSeeHtml('wire:stream.replace="flight-plan-progress"')
            ->assertSeeHtml('aria-label="Flight plan upload progress"')
            ->assertSeeHtml('x-bind:value="uploadProgress"')
            ->assertSeeText('Confirming upload…')
            ->assertSeeText('Upload sent. Waiting for confirmation…')
            ->assertSeeText('Large documents and scanned pages may take longer. Keep this page open.')
            ->assertDontSee('bugs@example.test')
            ->assertDontSeeText('Extract route')
            ->assertDontSeeText('Extracted flight plan');

        $this->assertFalse($component->viewData('isResultsView'));
    }

    public function test_it_validates_pdf_uploads_and_clears_the_error_when_the_file_changes(): void
    {
        config()->set('mail.admin_address', 'bugs@example.test');

        $this->assertSame(
            ['required', 'file', 'max:25600'],
            config('livewire.temporary_file_upload.rules'),
        );

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->call('extractFlightPlan')
            ->assertHasErrors(['flightRelease' => 'required'])
            ->assertSee('Upload a flight release PDF to extract the route.')
            ->assertSeeHtml('mailto:bugs@example.test?subject=Upload%20bug%20report')
            ->assertSee('bugs@example.test')
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.txt', 8, 'text/plain'))
            ->assertHasErrors(['flightRelease' => 'mimes'])
            ->assertSee('Only PDF flight release uploads are supported.');

        $this->assertSame(0, ExtractRequest::query()->count());
    }
}
