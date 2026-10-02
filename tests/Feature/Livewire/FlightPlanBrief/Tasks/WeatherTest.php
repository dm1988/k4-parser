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

class WeatherTest extends FlightPlanBriefTestCase
{
    public function test_weather_renders_all_raw_airport_reports_and_raim_without_interpretation(): void
    {
        Storage::fake('user_flight_releases');
        $weather = [
            'departure' => [
                'airport' => 'PANC',
                'metars' => [
                    'METAR PANC 250553Z 22006KT 10SM FEW060 14/06 A2991',
                    'SPECI PANC 250520Z 24008KT 8SM -RA BKN050 13/07 A2992',
                ],
                'tafs' => ["TAF PANC 250521Z 2506/2612 28006KT P6SM BKN070\nFM250800 21005KT P6SM SCT080"],
            ],
            'destination' => [
                'airport' => 'KMIA',
                'metars' => ['METAR KMIA 250553Z 00000KT 10SM SCT250 25/22 A3003'],
                'tafs' => ['TAF KMIA 250539Z 2506/2612 VRB05KT P6SM FEW030 SCT250'],
            ],
            'alternate' => [
                'airport' => 'KRSW',
                'metars' => ['METAR KRSW 250553Z AUTO 11003KT 10SM CLR 23/22 A3005'],
                'tafs' => ['TAF KRSW 250521Z 2506/2606 VRB03KT P6SM SCT030 BKN250'],
            ],
            'raim' => 'PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM 1020Z TO 1240Z',
        ];

        $this->mock(ExtractFlightPlanData::class, function (MockInterface $mock) use ($weather): void {
            $this->expectOnce($mock, 'extractFile')->andReturn($this->parsedFlightPlan(weather: $weather));
        });
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightRelease', UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'))
            ->call('selectTask', FlightPlanTask::Weather->value)
            ->assertSet('activeTask', FlightPlanTask::Weather->value)
            ->assertSeeHtml('wire:key="flight-plan-task-panel-weather"')
            ->assertSeeText('Airport weather')
            ->assertSeeTextInOrder(['Departure', 'PANC', 'Destination', 'KMIA', 'Alternate', 'KRSW'])
            ->assertSeeText('METAR PANC 250553Z 22006KT 10SM FEW060 14/06 A2991')
            ->assertSeeText('SPECI PANC 250520Z 24008KT 8SM -RA BKN050 13/07 A2992')
            ->assertSeeText('TAF PANC 250521Z 2506/2612 28006KT P6SM BKN070')
            ->assertSeeHtml("TAF PANC 250521Z 2506/2612 28006KT P6SM BKN070\nFM250800 21005KT P6SM SCT080")
            ->assertSeeText('METAR KMIA 250553Z 00000KT 10SM SCT250 25/22 A3003')
            ->assertSeeText('TAF KMIA 250539Z 2506/2612 VRB05KT P6SM FEW030 SCT250')
            ->assertSeeText('METAR KRSW 250553Z AUTO 11003KT 10SM CLR 23/22 A3005')
            ->assertSeeText('TAF KRSW 250521Z 2506/2606 VRB03KT P6SM SCT030 BKN250')
            ->assertSeeText('PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM 1020Z TO 1240Z')
            ->assertSeeText('Raw reports only')
            ->assertDontSeeText('Its dedicated operational layout is scheduled in the next focused task.');
    }
}
