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

class EtopsTest extends FlightPlanBriefTestCase
{
    public function test_etops_uses_a_dedicated_typed_layout_and_renders_the_source_rating_in_the_header(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')->andReturn($this->parsedFlightPlan(etops: [
                'section_present' => true,
                'applicability' => 'confirmed_etops',
                'rating_minutes' => 180,
            ]));
        });
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSeeText('ETOPS 180')
            ->assertSeeHtml('wire:key="flight-plan-overview-card-etops"')
            ->assertSeeText('1 ETP point')
            ->assertSeeHtml('aria-label="ETOPS time: 180 min"')
            ->assertSeeText('180')
            ->assertSeeHtml('wire:key="flight-plan-task-nav-etops"')
            ->assertSeeHtml('aria-label="ETOPS: 1 equal-time point"')
            ->call('selectTask', FlightPlanTask::Etops->value)
            ->assertSet('activeTask', FlightPlanTask::Etops->value)
            ->assertSeeHtml('wire:key="flight-plan-task-panel-etops"')
            ->assertSeeText('ETOPS source data')
            ->assertSeeText('Yes')
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

    public function test_confirmed_non_etops_hides_the_card_navigation_and_detail_workspace(): void
    {
        Storage::fake('user_flight_releases');
        $extractedRouteData = [
            ...$this->flightPlan(),
            'etps' => [],
            'eent_coordinates' => null,
            'eexp_coordinates' => null,
        ];

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock) use ($extractedRouteData): void {
            $this->expectOnce($mock, 'extractFile')->andReturn($this->parsedFlightPlan(
                extractedRouteData: $extractedRouteData,
                etops: [
                    'section_present' => true,
                    'applicability' => 'confirmed_non_etops',
                    'rating_minutes' => null,
                ],
            ));
        });
        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class);

        $component
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSet('activeTask', FlightPlanTask::Overview->value)
            ->assertDontSeeHtml('wire:key="flight-plan-overview-card-etops"')
            ->assertDontSeeText('ETP points')
            ->assertDontSeeHtml('wire:key="flight-plan-task-nav-etops"')
            ->assertSeeTextInOrder(['ETOPS', 'Non ETOPS'])
            ->assertSeeHtml('bg-[#1B365D]/10 text-[#1B365D] dark:bg-blue-400/15 dark:text-blue-200');

        $component
            ->call('selectTask', FlightPlanTask::Etops->value)
            ->assertSet('activeTask', FlightPlanTask::Overview->value)
            ->assertDontSeeHtml('wire:key="flight-plan-task-panel-etops"')
            ->assertDontSeeText('ETOPS source data');
    }
}
