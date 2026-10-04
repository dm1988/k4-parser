<?php

namespace Tests\Feature\Livewire\FlightPlanBrief\Workspace;

use App\Actions\ShouldPromptForCoffee;
use App\Enums\FlightPlanTask;
use App\Livewire\FlightPlanBrief;
use App\Models\User;
use App\Services\FlightPlan\Extractor\ExtractFlightPlanData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\Feature\Livewire\FlightPlanBrief\FlightPlanBriefTestCase;

class NavigationTest extends FlightPlanBriefTestCase
{
    public function test_route_enabled_extraction_navigates_to_the_canonical_overview(): void
    {
        Storage::fake('user_flight_releases');
        $user = User::factory()->admin()->create();

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan());
        });
        $this->mock(ShouldPromptForCoffee::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'handle')->andReturn(false);
        });

        $component = Livewire::actingAs($user)
            ->test(FlightPlanBrief::class, ['usesTaskRoutes' => true])
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertHasNoErrors()
            ->assertSet('activeTask', FlightPlanTask::Overview->value)
            ->assertRedirect(route('flight-release.task', ['task' => 'overview']));

        $this->assertIsString($component->get('flightPlanKey'));

        $this->get(route('flight-release.task', ['task' => 'overview']))
            ->assertOk()
            ->assertDontSeeText('Flight plan brief ready. Upload and extraction completed successfully.');
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
                    static fn (FlightPlanTask $task): bool => ! in_array($task, [
                        FlightPlanTask::ReviewMelCdl,
                        FlightPlanTask::SlotTimes,
                    ], true),
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
            ->assertSeeHtml('x-data="flightPlanTaskMenu"')
            ->assertSeeHtml('aria-label="Open task menu"')
            ->assertSeeHtml('aria-label="Close task menu"')
            ->assertSeeHtml('x-bind:aria-expanded="open.toString()"')
            ->assertSeeHtml('aria-controls="flight-plan-mobile-task-menu"')
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml('fixed inset-0 z-[60]')
            ->assertSeeHtml('motion-reduce:transition-none')
            ->assertSeeHtml('data-flight-plan-active-task')
            ->assertSeeHtml('border-[#C5A059]')
            ->assertSeeHtml('ms-auto flex shrink-0 items-center justify-end')
            ->assertSeeHtml('sr-only lg:not-sr-only lg:flex')
            ->assertDontSeeHtml('flex gap-1 overflow-x-auto p-2')
            ->assertSeeHtml('lg:grid-cols-[15rem_minmax(0,1fr)]')
            ->assertSeeHtml('wire:key="flight-plan-task-panel-overview"')
            ->assertSeeText('CKS241')
            ->assertSeeText('May 25, 2026')
            ->assertSeeText('B777-200F')
            ->assertSeeText('N774CK')
            ->assertDontSeeText('Tail N774CK')
            ->assertDontSeeText('ETD (UTC)')
            ->assertDontSeeText('ETA (UTC)')
            ->assertDontSeeText('Approved slots')
            ->assertDontSeeText('Slot times')
            ->assertDontSeeText('Review Slot Times')
            ->assertDontSeeHtml('wire:key="flight-plan-overview-card-slot_times"')
            ->assertDontSeeHtml('wire:key="flight-plan-task-nav-slot_times"')
            ->assertDontSeeHtml('wire:target="selectTask(\'slot_times\')"')
            ->assertSeeText('Release revision')
            ->assertSeeText('3');

        $flightPlanKey = $component->get('flightPlanKey');

        $component
            ->call('selectTask', FlightPlanTask::JeppPdPro->value)
            ->assertSet('activeTask', FlightPlanTask::JeppPdPro->value)
            ->assertSeeHtml('wire:key="flight-plan-task-panel-jepp_pd_pro"')
            ->assertSeeText('Extracted flight plan')
            ->assertSeeText('Departure runway')
            ->assertSeeTextInOrder(['Route', 'ETOPS critical points'])
            ->assertSeeText('ETOPS critical points')
            ->assertSeeText('DCT Q139 TEST')
            ->assertDontSeeText('Not supported yet');

        $component
            ->call('selectTask', FlightPlanTask::SlotTimes->value)
            ->assertSet('activeTask', FlightPlanTask::JeppPdPro->value);

        $component
            ->call('$refresh')
            ->assertSet('activeTask', FlightPlanTask::JeppPdPro->value);

        $component
            ->call('selectTask', 'untrusted-task')
            ->assertSet('activeTask', FlightPlanTask::JeppPdPro->value);

        $component
            ->call('selectTask', FlightPlanTask::Fms->value)
            ->assertSet('activeTask', FlightPlanTask::Fms->value)
            ->assertSeeText('FMS route setup')
            ->assertSeeTextInOrder(['DCT', 'Q139', 'TEST'])
            ->assertDontSee('data-copy-target=', escape: false);

        $this->assertSame($flightPlanKey, $component->get('flightPlanKey'));
    }
}
