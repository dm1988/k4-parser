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

class DispatcherNotesTest extends FlightPlanBriefTestCase
{
    public function test_dispatcher_notes_render_in_source_order_with_a_neutral_task_counter(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')->andReturn($this->parsedFlightPlan(
                dispatcherNotes: [
                    'FUEL BURN INCLUDES 0.5% PENALTY FOR PERISHABLES',
                    "SLOT TIMES:\n- N/A",
                ],
            ));
        });

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSeeHtml('wire:key="flight-plan-task-nav-notes"')
            ->assertSeeHtml('aria-label="Notes: 2 notes"')
            ->assertSeeHtml('bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200')
            ->call('selectTask', FlightPlanTask::Notes->value)
            ->assertSet('activeTask', FlightPlanTask::Notes->value);

        $html = $component->html();

        $this->assertStringContainsString('- N/A', $html);
        $this->assertLessThan(
            strpos($html, 'SLOT TIMES:'),
            strpos($html, 'FUEL BURN INCLUDES 0.5% PENALTY FOR PERISHABLES'),
        );
        $this->assertStringNotContainsString('Note 1', $html);
    }
}
