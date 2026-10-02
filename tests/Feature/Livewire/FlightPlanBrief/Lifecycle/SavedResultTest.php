<?php

namespace Tests\Feature\Livewire\FlightPlanBrief\Lifecycle;

use App\Actions\ShouldPromptForCoffee;
use App\Enums\FlightPlanTask;
use App\Exceptions\FlightRouteNotFoundException;
use App\Livewire\FlightPlanBrief;
use App\Models\FlightPlanResult;
use App\Models\User;
use App\Services\FlightPlan\Extractor\ExtractFlightPlanData;
use App\Services\Infrastructure\FlightPlanResultStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\Feature\Livewire\FlightPlanBrief\FlightPlanBriefTestCase;

class SavedResultTest extends FlightPlanBriefTestCase
{
    public function test_a_fresh_mount_restores_the_saved_result_beyond_the_former_cache_ttl_without_reparsing(): void
    {
        Storage::fake('user_flight_releases');
        Config::set('cache.extracted_results_ttl', 1);
        $user = User::factory()->admin()->create();

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan());
        });
        $this->mock(ShouldPromptForCoffee::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'handle')->andReturn(false);
        });

        $firstComponent = Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSeeText('Operational support status');

        $flightPlanKey = $firstComponent->get('flightPlanKey');
        $this->assertIsString($flightPlanKey);

        $this->travel(61)->minutes();
        Cache::flush();

        Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->assertSet('flightPlanKey', $flightPlanKey)
            ->assertSet('activeTask', FlightPlanTask::Overview->value)
            ->assertSeeText('Operational support status')
            ->assertSeeText('PANC');
    }

    public function test_a_failed_replacement_upload_preserves_the_previous_saved_result(): void
    {
        Storage::fake('user_flight_releases');
        $user = User::factory()->admin()->create();

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $mock->shouldReceive('extractFile')
                ->once()
                ->andReturn($this->parsedFlightPlan());
            $mock->shouldReceive('extractFile')
                ->once()
                ->andThrow(FlightRouteNotFoundException::routeSegmentMissing());
        });
        $this->mock(ShouldPromptForCoffee::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'handle')->andReturn(false);
        });

        $component = Livewire::actingAs($user)
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('first-flight-release.pdf', 120, 'application/pdf'))
            ->assertSeeText('Operational support status');

        $flightPlanKey = $component->get('flightPlanKey');
        $this->assertIsString($flightPlanKey);

        $component
            ->set('flightRelease', UploadedFile::fake()->create('replacement-flight-release.pdf', 120, 'application/pdf'))
            ->assertSet('flightPlanKey', $flightPlanKey)
            ->assertSet('flightRelease', null)
            ->assertHasErrors(['flightRelease'])
            ->assertSeeText('Operational support status');

        $this->assertSame(1, FlightPlanResult::query()->count());
        $this->assertIsArray(app(FlightPlanResultStore::class)->get($user, $flightPlanKey));
        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());
    }

    public function test_a_missing_stored_result_derives_the_upload_view_without_mutating_component_state(): void
    {
        Storage::fake('user_flight_releases');
        $user = User::factory()->admin()->create();

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')
                ->andReturn($this->parsedFlightPlan());
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

        app(FlightPlanResultStore::class)->delete($user, $flightPlanKey);

        $component
            ->call('$refresh')
            ->assertSet('flightPlanKey', $flightPlanKey)
            ->assertSeeText('Drop your flight plan here')
            ->assertDontSeeText('Extracted flight plan');

        $this->assertFalse($component->viewData('isResultsView'));
    }
}
