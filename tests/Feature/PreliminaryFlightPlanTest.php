<?php

namespace Tests\Feature;

use App\Actions\FlightPlan\BuildFlightPlanData;
use App\Actions\FlightPlan\BuildFlightPlanPageData;
use App\DTOs\ReleaseStatusData;
use App\Enums\EtopsApplicability;
use App\Enums\FlightPlanTask;
use App\Enums\ReleaseStatus;
use App\Exceptions\FlightRouteNotFoundException;
use App\Livewire\FlightPlanBrief;
use App\Models\FlightPlanResult;
use App\Models\User;
use App\Services\Clients\AirportLookupClient;
use App\Services\FlightPlan\Extractor\ExtractFlightPlanData;
use App\Services\FlightPlan\Extractor\FlightPlanTextExtractor;
use App\Services\FlightPlan\FlightPlanResultSerializer;
use App\Services\Infrastructure\FlightPlanResultStore;
use App\View\Models\FlightReleasePageViewModelFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Livewire\FlightPlanBrief\FlightPlanBriefTestCase;

class PreliminaryFlightPlanTest extends FlightPlanBriefTestCase
{
    private const string WARNING = 'PRELIMINARY FLIGHT PLAN - PAYLOAD ESTIMATED - DO NOT FUEL AIRCRAFT';

    public function test_detection_survives_upload_encrypted_storage_and_refresh_without_exposing_raw_evidence(): void
    {
        Storage::fake('user_flight_releases');
        $user = User::factory()->admin()->create();
        $this->mock(AirportLookupClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('resolveByIcaos')->andReturn([]);
        });
        $this->mock(FlightPlanTextExtractor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('extract')->once()->andReturn(
                '***'.self::WARNING."***KALITTA AIR\nFINAL DEST KMIA\nRELEASED IAW OPS SPEC B044\n"
                .'(FPL-CKS241-IS-B77L/H-SDE2E3FGHIJ1J4J5M1P2RWXYZ/LB1D1G1-PANC1040-N0489F330 DCT TEST-KMIA0712 KRSW-PBN/A1L1B1C1D1O1S2)',
            );
        });

        $component = Livewire::actingAs($user)->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('release.pdf', 120, 'application/pdf'))
            ->assertHasNoErrors()
            ->assertSeeText('Preliminary flight plan')
            ->assertSeeText('Payload estimated — Do not fuel aircraft')
            ->assertDontSeeHtml('flight-plan-overview-card-fuel_score');

        $store = app(FlightPlanResultStore::class);
        $key = $component->get('flightPlanKey');
        $saved = $store->get($user, $key);
        $rebuilt = app(BuildFlightPlanPageData::class)->handle($saved);
        $this->assertSame(ReleaseStatus::Preliminary, $rebuilt?->flightPlan->releaseStatus->status);
        $this->assertSame(self::WARNING, $rebuilt?->flightPlan->releaseStatus->sourceWarning);
        $this->assertSame('b44', $saved['flight_plan_data']['releaseAuthorization']['operationsSpecification']);
        $this->assertSame('PANC', $rebuilt?->flightPlan->route->departure->value);
        $this->assertStringNotContainsString(self::WARNING, json_encode($component->getData(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString(self::WARNING, FlightPlanResult::query()->sole()->getRawOriginal('payload'));
        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());

        Livewire::test(FlightPlanBrief::class)
            ->assertSet('flightPlanKey', $key)
            ->assertSeeText('Preliminary flight plan')
            ->assertDontSeeHtml('flight-plan-overview-card-fuel_score');
    }

    #[DataProvider('taskRoutes')]
    public function test_the_document_warning_is_visible_on_every_direct_task_route(FlightPlanTask $task): void
    {
        $user = User::factory()->admin()->create();
        app(FlightPlanResultStore::class)->save($user, $this->savedPayload(new ReleaseStatusData(ReleaseStatus::Preliminary, self::WARNING)));

        $this->actingAs($user)->get(route('flight-release.task', ['task' => $task->routeSlug()]))
            ->assertOk()
            ->assertSeeText('Preliminary flight plan')
            ->assertSeeText('Payload estimated — Do not fuel aircraft');
    }

    /** @return array<string, array{FlightPlanTask}> */
    public static function taskRoutes(): array
    {
        return array_combine(
            array_map(static fn (FlightPlanTask $task): string => $task->value, FlightPlanTask::cases()),
            array_map(static fn (FlightPlanTask $task): array => [$task], FlightPlanTask::cases()),
        );
    }

    public function test_successful_replacements_replace_status_together_with_values_and_failed_upload_preserves_them(): void
    {
        Storage::fake('user_flight_releases');
        $user = User::factory()->admin()->create();
        $store = app(FlightPlanResultStore::class);
        $oldKey = $store->save($user, $this->savedPayload(new ReleaseStatusData(ReleaseStatus::Preliminary, self::WARNING)));
        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $mock->shouldReceive('extractFile')->once()->ordered()
                ->andThrow(FlightRouteNotFoundException::pdfCouldNotBeRead());
            $mock->shouldReceive('extractFile')->once()->ordered()->andReturn($this->parsedFlightPlan(
                fuel: ['ramp' => ['amount' => 99000.0, 'unit' => 'lb']],
                releaseStatus: new ReleaseStatusData(ReleaseStatus::NotMarkedPreliminary),
            ));
            $mock->shouldReceive('extractFile')->once()->ordered()->andReturn($this->parsedFlightPlan(
                releaseStatus: new ReleaseStatusData(ReleaseStatus::Preliminary, self::WARNING),
            ));
        });

        $component = Livewire::actingAs($user)->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('bad.pdf', 120, 'application/pdf'))
            ->assertHasErrors('flightRelease')
            ->assertSet('flightPlanKey', $oldKey)
            ->assertSeeText('Preliminary flight plan');
        $this->assertSame('preliminary', $store->get($user, $oldKey)['flight_plan_data']['releaseStatus']['status']);

        $component->set('flightRelease', UploadedFile::fake()->create('preliminary-filename.pdf', 120, 'application/pdf'))
            ->assertHasNoErrors()
            ->assertDontSeeText('Preliminary flight plan')
            ->assertSeeText('Ramp Fuel');
        $newKey = $component->get('flightPlanKey');
        $this->assertNotSame($oldKey, $newKey);
        $this->assertNull($store->get($user, $oldKey));
        $this->assertSame(99000, $store->get($user, $newKey)['flight_plan_data']['fuelPlan']['ramp']['amount']);
        $this->assertSame('not_marked_preliminary', $store->get($user, $newKey)['flight_plan_data']['releaseStatus']['status']);

        $component->set('flightRelease', UploadedFile::fake()->create('another.pdf', 120, 'application/pdf'))
            ->assertHasNoErrors()
            ->assertSeeText('Preliminary flight plan')
            ->assertDontSeeHtml('flight-plan-overview-card-fuel_score');
    }

    public function test_malformed_legacy_status_keeps_the_saved_brief_and_existing_overview_behavior(): void
    {
        $user = User::factory()->admin()->create();
        $result = $this->savedPayload(new ReleaseStatusData);
        $result['flight_plan_data']['releaseStatus'] = ['status' => ['invalid']];
        app(FlightPlanResultStore::class)->save($user, $result);

        Livewire::actingAs($user)->test(FlightPlanBrief::class)
            ->assertSeeText('Release status not determined')
            ->assertSeeText('Re-upload the flight plan')
            ->assertSeeText('Ramp Fuel')
            ->assertDontSeeText('Preliminary flight plan');
    }

    public function test_the_supplied_private_pdf_contains_the_supported_warning(): void
    {
        $path = storage_path('app/private/prelim_flight_release/CKS052425KORD.pdf');

        if (! is_file($path)) {
            $this->markTestSkipped('The private preliminary flight-release fixture is not available.');
        }

        $this->mock(AirportLookupClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('resolveByIcaos')->andReturn([]);
        });

        $parsed = app(ExtractFlightPlanData::class)->extractFile($path);

        $this->assertSame(ReleaseStatus::Preliminary, $parsed->releaseStatus->status);
        $this->assertSame(self::WARNING, $parsed->releaseStatus->sourceWarning);
    }

    #[DataProvider('fuelUnits')]
    public function test_save_and_reload_preserve_source_values_units_and_unknown_etops_applicability(string $unit): void
    {
        $user = User::factory()->admin()->create();
        $parsed = $this->parsedFlightPlan(
            extractedRouteData: [...$this->flightPlan(), 'etps' => [], 'eent_coordinates' => null, 'eexp_coordinates' => null],
            fuel: ['ramp' => ['amount' => 120000.5, 'unit' => $unit]],
            weightBalance: ['planned_payload' => ['amount' => 0, 'unit' => $unit, 'status' => 'confirmed']],
            releaseStatus: new ReleaseStatusData(ReleaseStatus::Preliminary, self::WARNING),
        );
        $plan = app(BuildFlightPlanData::class)->handle($parsed);
        $serialized = app(FlightPlanResultSerializer::class)->serialize($plan);
        $store = app(FlightPlanResultStore::class);
        $key = $store->save($user, $serialized);

        $rebuilt = app(BuildFlightPlanPageData::class)->handle($store->get($user, $key));

        $this->assertNotNull($rebuilt);
        $this->assertSame($plan->toArray(), $rebuilt->flightPlan->toArray());
        $this->assertSame(120000.5, $rebuilt->flightPlan->fuelPlan?->ramp?->amount);
        $this->assertSame($unit, $rebuilt->flightPlan->fuelPlan?->ramp?->unit);
        $this->assertNull($rebuilt->flightPlan->etops);
        $this->assertSame(EtopsApplicability::Unknown,
            app(FlightReleasePageViewModelFactory::class)->make($rebuilt)->etopsApplicability(),
        );
    }

    /** @return array<string, array{string}> */
    public static function fuelUnits(): array
    {
        return ['pounds' => ['lb'], 'kilograms' => ['kg']];
    }

    /** @return array<string, mixed> */
    private function savedPayload(ReleaseStatusData $status): array
    {
        return app(FlightPlanResultSerializer::class)->serialize(app(BuildFlightPlanData::class)->handle($this->parsedFlightPlan(
            fuel: ['ramp' => ['amount' => 120000.0, 'unit' => 'lb']],
            releaseStatus: $status,
            etops: ['rating_minutes' => 207],
            schedule: ['slots' => [[
                'direction' => 'departure', 'airport' => 'PANC', 'instant_utc' => '2026-05-25T18:45:00Z',
                'source_time' => '1845Z', 'tolerance_minutes' => 30,
            ]]],
        )));
    }
}
