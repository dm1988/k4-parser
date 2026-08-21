<?php

namespace Tests\Unit;

use App\DTOs\ParsedFlightPlanData;
use App\Services\Clients\AirportLookupClient;
use App\Services\FlightPlan\Extractor\ExtractFlightPlanData;
use App\Services\FlightPlan\Extractor\FlightFuelExtractor;
use App\Services\FlightPlan\Extractor\FlightIdentityExtractor;
use App\Services\FlightPlan\Extractor\FlightPlanTextExtractor;
use App\Services\FlightPlan\Extractor\FlightRouteExtractor;
use App\Services\FlightPlan\Extractor\FlightScheduleExtractor;
use Illuminate\Contracts\Cache\Repository;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class ExtractFlightPlanDataTest extends TestCase
{
    public function test_it_passes_one_text_payload_to_each_focused_extractor(): void
    {
        $text = 'normalized flight release text';
        $textExtractor = $this->createMock(FlightPlanTextExtractor::class);
        $textExtractor->expects($this->once())->method('extract')->with('/tmp/release.pdf')->willReturn($text);
        $identityExtractor = $this->createMock(FlightIdentityExtractor::class);
        $identityExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => $this->identity(),
            'source_fragments' => ['identity_header' => 'header'],
        ]);
        $scheduleExtractor = $this->createMock(FlightScheduleExtractor::class);
        $scheduleExtractor->expects($this->once())->method('extract')->with($text, '2026-05-25')->willReturn([
            'data' => $this->schedule(),
            'source_fragments' => [],
        ]);
        $routeExtractor = $this->createMock(FlightRouteExtractor::class);
        $routeExtractor->expects($this->once())->method('extractFlightPlanDataFromText')->with($text)->willReturn($this->legacyRoute());
        $fuelExtractor = $this->createMock(FlightFuelExtractor::class);
        $fuelExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => $this->fuel(),
            'source_fragments' => ['fuel_unit' => 'lb'],
        ]);

        $parsed = (new ExtractFlightPlanData(
            $textExtractor,
            $identityExtractor,
            $scheduleExtractor,
            $routeExtractor,
            $fuelExtractor,
        ))->extractFile('/tmp/release.pdf');

        $this->assertInstanceOf(ParsedFlightPlanData::class, $parsed);
        $this->assertSame('CKS256', $parsed->identity['flight_number']);
        $this->assertSame(5549, $parsed->route['distance_nautical_miles']);
        $this->assertSame('lb', $parsed->sourceFragments['fuel_unit']);
        $this->assertSame('FL 340', $parsed->legacy['initial_altitude']);
    }

    public function test_private_sample_characterizes_confirmed_normalized_fields(): void
    {
        $samplePath = storage_path('app/private/test_data/CKS025625KLAX.pdf');

        if (! is_file($samplePath)) {
            $this->markTestSkipped('The private flight-release PDF fixture is not available.');
        }

        $airportLookupClient = $this->createMock(AirportLookupClient::class);
        $airportLookupClient->method('lookupByIcao')->willReturn(null);
        $textExtractor = new FlightPlanTextExtractor(new Parser, app(Repository::class));
        $extractor = new ExtractFlightPlanData(
            $textExtractor,
            new FlightIdentityExtractor,
            new FlightScheduleExtractor,
            new FlightRouteExtractor($textExtractor, $airportLookupClient),
            new FlightFuelExtractor,
        );

        $parsed = $extractor->extractFile($samplePath);

        $this->assertSame('CKS256', $parsed->identity['flight_number']);
        $this->assertSame('109546', $parsed->identity['trip_number']);
        $this->assertSame('62930', $parsed->identity['recall_number']);
        $this->assertSame('N774CK', $parsed->identity['tail_number']);
        $this->assertSame('2026-05-25', $parsed->identity['flight_date']);
        $this->assertSame('2026-05-25T02:20:00+00:00', $parsed->schedule['etd_utc']);
        $this->assertSame(5549, $parsed->route['distance_nautical_miles']);
        $this->assertSame(['amount' => 216800.0, 'unit' => 'lb'], $parsed->fuel['ramp']);
        $this->assertSame(['amount' => 195116.0, 'unit' => 'lb'], $parsed->fuel['trip']);
        $this->assertNull($parsed->fuel['contingency']);
    }

    /** @return array{flight_number: string, trip_number: string, recall_number: string, aircraft_type: string, tail_number: string, flight_date: string, release_revision: null} */
    private function identity(): array
    {
        return [
            'flight_number' => 'CKS256',
            'trip_number' => '109546',
            'recall_number' => '62930',
            'aircraft_type' => 'B777-200F',
            'tail_number' => 'N774CK',
            'flight_date' => '2026-05-25',
            'release_revision' => null,
        ];
    }

    /** @return array{etd_utc: string, eta_utc: string, block_duration: null, report_time_utc: null, duty_end_utc: null, slot_times_utc: list<string>} */
    private function schedule(): array
    {
        return [
            'etd_utc' => '2026-05-25T02:20:00+00:00',
            'eta_utc' => '2026-05-25T14:50:00+00:00',
            'block_duration' => null,
            'report_time_utc' => null,
            'duty_end_utc' => null,
            'slot_times_utc' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function legacyRoute(): array
    {
        return [
            'departure' => 'KLAX',
            'destination' => 'RKSI',
            'alternate' => 'RKTU',
            'departure_airport' => null,
            'destination_airport' => null,
            'alternate_airport' => null,
            'departure_runway' => '25R',
            'arrival_runway' => '33R',
            'departure_sid' => 'SUMMR2',
            'arrival_star' => 'GUKDO2E',
            'distance_nautical_miles' => 5549,
            'etps' => [],
            'eent_coordinates' => null,
            'eexp_coordinates' => null,
            'initial_altitude' => 'FL 340',
            'duration' => '12h10m',
            'route' => 'DCT TEST',
        ];
    }

    /** @return array{ramp: null, taxi: null, takeoff: null, trip: null, contingency: null, alternate: null, final_reserve: null, estimated_landing: null} */
    private function fuel(): array
    {
        return array_fill_keys([
            'ramp', 'taxi', 'takeoff', 'trip', 'contingency', 'alternate', 'final_reserve', 'estimated_landing',
        ], null);
    }
}
