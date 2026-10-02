<?php

namespace Tests\Feature\Livewire\FlightPlanBrief\Lifecycle;

use App\Livewire\FlightPlanBrief;
use App\Models\ExtractRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Feature\Livewire\FlightPlanBrief\FlightPlanBriefTestCase;

class UploadTest extends FlightPlanBriefTestCase
{
    public function test_it_starts_on_the_upload_view_with_accessible_loading_states(): void
    {
        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->assertSet('flightRelease', null)
            ->assertSet('flightPlanKey', null)
            ->assertSet('extractionJustCompleted', false)
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
            ->assertDontSeeText('Extract route')
            ->assertDontSeeText('Extracted flight plan');

        $this->assertFalse($component->viewData('isResultsView'));
    }

    public function test_it_validates_pdf_uploads_and_clears_the_error_when_the_file_changes(): void
    {
        $this->assertSame(
            ['required', 'file', 'max:25600'],
            config('livewire.temporary_file_upload.rules'),
        );

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
}
