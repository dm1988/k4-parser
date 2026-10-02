<?php

namespace Tests\Feature\Livewire\FlightPlanBrief\Tasks;

use App\Enums\FlightPlanTask;
use App\Livewire\FlightPlanBrief;
use App\Models\Aircraft;
use App\Models\User;
use App\Services\FlightPlan\Extractor\ExtractFlightPlanData;
use App\Services\Infrastructure\FlightPlanResultStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\Feature\Livewire\FlightPlanBrief\FlightPlanBriefTestCase;

class WeightAndBalanceTest extends FlightPlanBriefTestCase
{
    public function test_weight_and_balance_renders_planned_values_independent_statuses_and_server_derived_ramp_weight(): void
    {
        Storage::fake('user_flight_releases');
        Aircraft::factory()->create([
            'tail_number' => 'N774CK',
            'max_ramp_weight' => 600000,
            'max_zero_fuel_weight' => 400000,
            'max_takeoff_weight' => 580000,
            'max_landing_weight' => 370000,
        ]);
        $user = User::factory()->admin()->create();

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')->andReturn($this->parsedFlightPlan(
                identity: [
                    'aircraft_type' => 'B777-200F',
                    'tail_number' => 'N774CK',
                ],
                fuel: [
                    'ramp' => ['amount' => 225500.0, 'unit' => 'lb'],
                    'taxi' => null,
                    'takeoff' => ['amount' => 223489.0, 'unit' => 'lb'],
                    'trip' => null,
                    'contingency' => null,
                    'alternate' => null,
                    'final_reserve' => null,
                    'estimated_landing' => null,
                ],
                weightBalance: [
                    'basic_operating_weight' => ['amount' => 335858, 'unit' => 'lb', 'status' => 'confirmed'],
                    'planned_payload' => ['amount' => null, 'unit' => 'lb', 'status' => 'conflict'],
                    'planned_zero_fuel_weight' => ['amount' => 353858, 'unit' => 'lb', 'status' => 'confirmed'],
                    'planned_takeoff_gross_weight' => ['amount' => 577347, 'unit' => 'lb', 'status' => 'confirmed'],
                    'planned_estimated_landing_weight' => ['amount' => 371893, 'unit' => 'lb', 'status' => 'confirmed'],
                ],
            ));
        });
        $component = Livewire::actingAs($user)->test(FlightPlanBrief::class);

        $component
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertSeeHtml('wire:key="flight-plan-overview-card-weight_and_balance"')
            ->assertSeeHtml('aria-label="3 operational weight alerts"')
            ->assertSeeHtml('border-red-500/30 border-l-4 border-l-red-500 bg-red-500/5 backdrop-blur')
            ->assertSeeHtml('font-mono text-5xl font-black leading-none text-[#0B0E14] dark:text-slate-100')
            ->assertSeeText('Heavy weight operation · 1 caution item · 1 exceeded item')
            ->assertSeeHtml('wire:click="selectTask(\'weight_and_balance\')"')
            ->assertSeeText('Verify Weight & Balance')
            ->call('selectTask', FlightPlanTask::WeightAndBalance->value)
            ->assertSet('activeTask', FlightPlanTask::WeightAndBalance->value)
            ->assertSeeText('Planned weights and structural limits')
            ->assertSeeText('Base & Payload')
            ->assertSeeText('Departure')
            ->assertSeeText('Arrival')
            ->assertSeeTextInOrder([
                'Base & Payload',
                'Zero-fuel weight',
                'Basic operating weight',
                'Payload',
                'Departure',
                'Ramp weight',
                'Takeoff gross weight',
                'Takeoff fuel',
                'Arrival',
                'Estimated landing weight',
            ])
            ->assertSeeText('Basic operating weight')
            ->assertSeeText('335,858')
            ->assertSeeText('Payload')
            ->assertSeeText('Conflict')
            ->assertSeeText('Zero-fuel weight')
            ->assertSeeText('Takeoff fuel')
            ->assertSeeText('223,489')
            ->assertSeeText('Ramp weight')
            ->assertSeeText('579,358')
            ->assertSeeText('Takeoff gross weight')
            ->assertSeeText('Estimated landing weight')
            ->assertSeeText('LB')
            ->assertSeeText('400,000')
            ->assertSeeText('600,000')
            ->assertSeeText('580,000')
            ->assertSeeText('370,000')
            ->assertSeeText('88.5% OF 400,000 LB LIMIT')
            ->assertSeeText('96.6% OF 600,000 LB LIMIT')
            ->assertSeeText('99.5% OF 580,000 LB LIMIT')
            ->assertSeeText('100.5% OF 370,000 LB LIMIT')
            ->assertSeeHtml('aria-label="Zero-fuel weight utilization"')
            ->assertSeeHtml('aria-label="Ramp weight utilization"')
            ->assertSeeHtml('aria-label="Takeoff gross weight utilization"')
            ->assertSeeHtml('aria-label="Estimated landing weight utilization"')
            ->assertSeeHtml('relative h-3.5 overflow-hidden rounded-full')
            ->assertSeeHtml('pointer-events-none absolute inset-0 flex items-center px-1.5 pt-0.5 font-mono text-[8px] font-extrabold leading-none tracking-wide')
            ->assertSeeHtml('truncate rounded-full px-1.5 text-white')
            ->assertDontSeeHtml('bg-[#0B0E14]/75')
            ->assertDontSeeHtml('px-1.5 text-white shadow-sm')
            ->assertSeeHtml('cc-weight-progress-safe')
            ->assertSeeHtml('cc-weight-progress-heavy')
            ->assertSeeHtml('cc-weight-progress-caution')
            ->assertSeeHtml('cc-weight-progress-exceeded')
            ->assertSeeHtml('aria-label="Weight &amp; Balance: 3 operational weight alerts"')
            ->assertSeeHtml('cc-weight-badge cc-weight-badge-exceeded text-white')
            ->assertSeeText('Derived server-side from confirmed zero-fuel weight and ramp fuel.')
            ->assertDontSeeText('Limit unavailable')
            ->assertDontSeeText('Within operating margin')
            ->assertDontSeeText('Heavy operation')
            ->assertDontSeeText('Near structural limit')
            ->assertDontSeeText('Structural limit exceeded')
            ->assertDontSeeText('Confirmed');

        $flightPlanKey = $component->get('flightPlanKey');
        $this->assertIsString($flightPlanKey);
        $storedFlightPlan = app(FlightPlanResultStore::class)->get(
            $user,
            $flightPlanKey,
        );

        $this->assertSame(
            580000,
            $storedFlightPlan['flight_plan_data']['weightBalance']['plannedTakeoffGrossWeight']['permittedLimit']['amount'] ?? null,
        );
    }

    public function test_weight_and_balance_reports_unavailable_limits_for_an_unmatched_aircraft(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'extractFile')->andReturn($this->parsedFlightPlan(
                identity: [
                    'aircraft_type' => 'B777-200F',
                    'tail_number' => 'N999ZZ',
                ],
                fuel: [
                    'ramp' => ['amount' => 225500.0, 'unit' => 'lb'],
                    'taxi' => null,
                    'takeoff' => null,
                    'trip' => null,
                    'contingency' => null,
                    'alternate' => null,
                    'final_reserve' => null,
                    'estimated_landing' => null,
                ],
                weightBalance: [
                    'planned_zero_fuel_weight' => ['amount' => 353858, 'unit' => 'lb', 'status' => 'confirmed'],
                    'planned_takeoff_gross_weight' => ['amount' => 577347, 'unit' => 'lb', 'status' => 'confirmed'],
                    'planned_estimated_landing_weight' => ['amount' => 371893, 'unit' => 'lb', 'status' => 'confirmed'],
                ],
            ));
        });

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->assertDontSeeHtml('wire:key="flight-plan-overview-card-weight_and_balance"')
            ->call('selectTask', FlightPlanTask::WeightAndBalance->value)
            ->assertSeeText('Limit unavailable')
            ->assertDontSeeHtml('aria-label="Weight &amp; Balance:')
            ->assertDontSeeHtml('aria-label="Zero-fuel weight utilization"')
            ->assertDontSeeText('% of structural limit');
    }
}
