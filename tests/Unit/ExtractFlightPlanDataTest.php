<?php

namespace Tests\Unit;

use App\DTOs\AirportData;
use App\DTOs\ParsedFlightPlanData;
use App\Services\Clients\AirportLookupClient;
use App\Services\FlightPlan\Extractor\Etops\EtopsQualificationExtractor;
use App\Services\FlightPlan\Extractor\Etops\EtopsRouteExtractor;
use App\Services\FlightPlan\Extractor\ExtractFlightPlanData;
use App\Services\FlightPlan\Extractor\FlightCrewExtractor;
use App\Services\FlightPlan\Extractor\FlightFuelExtractor;
use App\Services\FlightPlan\Extractor\FlightIdentityExtractor;
use App\Services\FlightPlan\Extractor\FlightInitExtractor;
use App\Services\FlightPlan\Extractor\FlightPlanTextExtractor;
use App\Services\FlightPlan\Extractor\FlightRouteExtractor;
use App\Services\FlightPlan\Extractor\FlightScheduleExtractor;
use App\Services\FlightPlan\Extractor\GeneralDeclarationExtractor;
use App\Services\FlightPlan\Extractor\MaintenanceLogExtractor;
use App\Services\FlightPlan\Extractor\PdfImagePageTextExtractor;
use App\Services\FlightPlan\Extractor\ReleaseAuthorizationExtractor;
use App\Services\FlightPlan\Extractor\TakeoffLandingReportExtractor;
use App\Services\FlightPlan\Extractor\WaypointExtractor;
use App\Services\FlightPlan\Extractor\WeatherExtractor;
use App\Services\FlightPlan\Extractor\WeightBalanceExtractor;
use App\Services\Infrastructure\AirportCodeCache;
use App\Services\Schedule\Extractor\CrewListParser;
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
        $routeExtractor->expects($this->once())->method('extractFlightPlanDataFromText')->with($text)->willReturn($this->routeData());
        $fuelExtractor = $this->createMock(FlightFuelExtractor::class);
        $fuelExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => $this->fuel(),
            'source_fragments' => ['fuel_unit' => 'lb'],
        ]);
        $crewExtractor = $this->createMock(FlightCrewExtractor::class);
        $crewExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => $this->crewMembers(),
            'source_fragments' => ['flight_crew' => 'Alex Morgan CP YIP'],
        ]);
        $maintenanceLogExtractor = $this->createMock(MaintenanceLogExtractor::class);
        $maintenanceLogExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => $this->maintenance(),
            'source_fragments' => ['maintenance_log' => 'MEL 28-22-01'],
        ]);
        $takeoffLandingReportExtractor = $this->createMock(TakeoffLandingReportExtractor::class);
        $takeoffLandingReportExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => $this->takeoffLandingReport(),
            'source_fragments' => ['takeoff_landing_report' => 'private TLR evidence'],
        ]);
        $flightInitExtractor = $this->createMock(FlightInitExtractor::class);
        $flightInitExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => [
                'section_present' => true,
                'acars_init_date' => '11',
                'filed_initial_altitude' => 'F340',
                'fms_initial_altitude' => 'F290',
            ],
            'source_fragments' => [
                'flight_init_takeoff_landing_report' => 'private ACARS init evidence',
                'flight_init_filed_initial_altitude' => 'F340',
                'flight_init_fms_initial_altitude' => 'DEST RKSI 033.4 01.48 290 0896 P078',
            ],
        ]);
        $waypointExtractor = $this->createMock(WaypointExtractor::class);
        $waypointExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => [[
                'coordinate' => 'N51 36.5 E012 11.5',
                'identifier' => 'DP550',
                'time' => '002',
                'total_time' => '00.03',
            ]],
            'source_fragments' => ['computed_flight_plan_waypoints' => 'bounded waypoint evidence'],
        ]);
        $weatherExtractor = $this->createMock(WeatherExtractor::class);
        $weatherExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => [
                'departure' => [
                    'airport' => 'KLAX',
                    'metars' => ['METAR KLAX 242153Z 27011KT 10SM FEW020 19/11 A3000'],
                    'tafs' => ['TAF KLAX 241739Z 2418/2524 25007KT P6SM OVC020'],
                ],
                'destination' => null,
                'alternate' => null,
                'raim' => 'PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM 0200Z TO 0420Z',
            ],
            'source_fragments' => ['weather_departure' => 'private weather evidence'],
        ]);
        $weightBalanceExtractor = $this->createMock(WeightBalanceExtractor::class);
        $weightBalanceExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => [
                'basic_operating_weight' => ['amount' => 335858, 'unit' => 'lb', 'status' => 'confirmed'],
            ],
            'source_fragments' => ['weight_balance_basic_operating_weight' => 'private weight evidence'],
        ]);
        $etopsQualificationExtractor = $this->createMock(EtopsQualificationExtractor::class);
        $etopsQualificationExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => [
                'section_present' => true,
                'applicability' => 'confirmed_etops',
                'rating_minutes' => 180,
            ],
            'source_fragments' => ['etops_qualification' => 'ETOPS 180 ETOPS ALTERNATE AIRPORTS'],
        ]);
        $etopsRouteExtractor = $this->createMock(EtopsRouteExtractor::class);
        $etopsRouteExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => [
                'etps' => [[
                    'label' => 'ETP1',
                    'airports' => 'KSFO-PACD',
                    'coordinates' => 'N45 43.7 W143 53.1',
                    'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
                ]],
                'eent_coordinates' => 'N40 31.1 W131 22.6',
                'eexp_coordinates' => 'N45 19.3 E151 36.4',
            ],
            'source_fragments' => [],
        ]);
        $generalDeclarationExtractor = $this->createMock(GeneralDeclarationExtractor::class);
        $generalDeclarationExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => ['section_present' => true],
            'source_fragments' => ['general_declaration_signature' => 'private GENDEC signature'],
        ]);
        $releaseAuthorizationExtractor = $this->createMock(ReleaseAuthorizationExtractor::class);
        $releaseAuthorizationExtractor->expects($this->once())->method('extract')->with($text)->willReturn([
            'data' => ['operations_specification' => 'b44'],
            'source_fragments' => ['release_authorization' => 'RELEASED IAW OPS SPEC B044'],
        ]);

        $parsed = (new ExtractFlightPlanData(
            $textExtractor,
            $identityExtractor,
            $scheduleExtractor,
            $routeExtractor,
            $fuelExtractor,
            $crewExtractor,
            $maintenanceLogExtractor,
            $takeoffLandingReportExtractor,
            $flightInitExtractor,
            $waypointExtractor,
            $weatherExtractor,
            $weightBalanceExtractor,
            $etopsQualificationExtractor,
            $etopsRouteExtractor,
            $generalDeclarationExtractor,
            $releaseAuthorizationExtractor,
        ))->extractFile('/tmp/release.pdf');

        $this->assertInstanceOf(ParsedFlightPlanData::class, $parsed);
        $this->assertSame('CKS256', $parsed->identity['flight_number']);
        $this->assertSame(5549, $parsed->route['distance_nautical_miles']);
        $this->assertSame('Los Angeles International Airport', $parsed->route['departure_airport']?->name);
        $this->assertSame('lb', $parsed->sourceFragments['fuel_unit']);
        $this->assertTrue($parsed->maintenance->sectionPresent);
        $this->assertSame('Alex Morgan', $parsed->crewMembers->members[0]['name']);
        $this->assertSame('Alex Morgan CP YIP', $parsed->sourceFragments['flight_crew']);
        $this->assertSame('MEL 28-22-01', $parsed->sourceFragments['maintenance_log']);
        $this->assertSame(612400, $parsed->takeoffLandingReport['planned_takeoff_weight']['amount']);
        $this->assertSame('private TLR evidence', $parsed->sourceFragments['takeoff_landing_report']);
        $this->assertSame('11', $parsed->flightInit['acars_init_date']);
        $this->assertSame('F340', $parsed->flightInit['filed_initial_altitude']);
        $this->assertSame('F290', $parsed->flightInit['fms_initial_altitude']);
        $this->assertSame('F340', $parsed->sourceFragments['flight_init_filed_initial_altitude']);
        $this->assertSame('private ACARS init evidence', $parsed->sourceFragments['flight_init_takeoff_landing_report']);
        $this->assertSame('DEST RKSI 033.4 01.48 290 0896 P078', $parsed->sourceFragments['flight_init_fms_initial_altitude']);
        $this->assertSame('DP550', $parsed->waypoints[0]['identifier']);
        $this->assertSame('bounded waypoint evidence', $parsed->sourceFragments['computed_flight_plan_waypoints']);
        $this->assertSame('KLAX', $parsed->weather['departure']['airport']);
        $this->assertSame('private weather evidence', $parsed->sourceFragments['weather_departure']);
        $this->assertSame(335858, $parsed->weightBalance['basic_operating_weight']['amount']);
        $this->assertSame('private weight evidence', $parsed->sourceFragments['weight_balance_basic_operating_weight']);
        $this->assertSame('ETP1', $parsed->etops['etps'][0]['label']);
        $this->assertSame('N40 31.1 W131 22.6', $parsed->etops['eent_coordinates']);
        $this->assertSame('N45 19.3 E151 36.4', $parsed->etops['eexp_coordinates']);
        $this->assertSame(180, $parsed->etops['rating_minutes']);
        $this->assertSame('confirmed_etops', $parsed->etops['applicability']);
        $this->assertSame('ETOPS 180 ETOPS ALTERNATE AIRPORTS', $parsed->sourceFragments['etops_qualification']);
        $this->assertTrue($parsed->generalDeclaration['section_present']);
        $this->assertSame('private GENDEC signature', $parsed->sourceFragments['general_declaration_signature']);
        $this->assertSame('b44', $parsed->releaseAuthorization['operations_specification']);
        $this->assertSame('RELEASED IAW OPS SPEC B044', $parsed->sourceFragments['release_authorization']);
        $this->assertSame('12h10m', $parsed->schedule['block_duration']);
    }

    public function test_private_sample_characterizes_confirmed_normalized_fields(): void
    {
        $samplePath = storage_path('app/private/test_data/CKS025625KLAX.pdf');

        if (! is_file($samplePath)) {
            $this->markTestSkipped('The private flight-release PDF fixture is not available.');
        }

        $airportLookupClient = $this->createMock(AirportLookupClient::class);
        $airportLookupClient->method('lookupByIcao')->willReturn(null);
        $textExtractor = new FlightPlanTextExtractor(
            new Parser,
            app(Repository::class),
            new PdfImagePageTextExtractor,
        );
        $extractor = new ExtractFlightPlanData(
            $textExtractor,
            new FlightIdentityExtractor,
            new FlightScheduleExtractor,
            new FlightRouteExtractor($textExtractor, $airportLookupClient, app(AirportCodeCache::class)),
            new FlightFuelExtractor,
            new FlightCrewExtractor(new CrewListParser),
            new MaintenanceLogExtractor,
            new TakeoffLandingReportExtractor,
            new FlightInitExtractor,
            new WaypointExtractor,
            new WeatherExtractor,
            new WeightBalanceExtractor,
            new EtopsQualificationExtractor,
            new EtopsRouteExtractor,
            new GeneralDeclarationExtractor,
            new ReleaseAuthorizationExtractor,
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
        $this->assertContains(
            $parsed->etops['applicability'],
            ['confirmed_etops', 'confirmed_non_etops', 'unknown'],
        );
        $this->assertObjectNotHasProperty('etopsApplicability', $parsed->maintenance);
        $this->assertTrue($parsed->takeoffLandingReport['section_present']);
        $this->assertSame('KLAX', $parsed->takeoffLandingReport['airport']);
        $this->assertSame('25R-E957F', $parsed->takeoffLandingReport['planned_runway']);
        $this->assertSame(['amount' => 618100, 'unit' => 'lb'], $parsed->takeoffLandingReport['planned_takeoff_weight']);
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

    /** @return array{etd_utc: string, eta_utc: string, block_duration: string, report_time_utc: null, duty_end_utc: null, slot_times_utc: list<string>} */
    private function schedule(): array
    {
        return [
            'etd_utc' => '2026-05-25T02:20:00+00:00',
            'eta_utc' => '2026-05-25T14:50:00+00:00',
            'block_duration' => '12h10m',
            'report_time_utc' => null,
            'duty_end_utc' => null,
            'slot_times_utc' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function routeData(): array
    {
        return [
            'departure' => 'KLAX',
            'destination' => 'RKSI',
            'alternate' => 'RKTU',
            'departure_airport' => new AirportData(
                'KLAX',
                'LAX',
                'Los Angeles International Airport',
                'Los Angeles',
                'California',
                'United States',
            ),
            'destination_airport' => null,
            'alternate_airport' => null,
            'departure_runway' => '25R',
            'arrival_runway' => '33R',
            'departure_sid' => 'SUMMR2',
            'arrival_star' => 'GUKDO2E',
            'distance_nautical_miles' => 5549,
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

    /** @return list<array{name: string, role: string, base: string, employee_number: string}> */
    private function crewMembers(): array
    {
        return [[
            'name' => 'Alex Morgan',
            'role' => 'CP',
            'base' => 'YIP',
            'employee_number' => '4827',
        ]];
    }

    /** @return array{section_present: true, items: list<array{type: string, number: string, description: string, reference: string, status: string, limitations: null, procedures: null}>} */
    private function maintenance(): array
    {
        return [
            'section_present' => true,
            'items' => [[
                'type' => 'MEL',
                'number' => '28-22-01',
                'description' => 'Center tank override pump inoperative.',
                'reference' => '1042',
                'status' => 'OPEN',
                'limitations' => null,
                'procedures' => null,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function takeoffLandingReport(): array
    {
        return [
            'section_present' => true,
            'source_type' => 'takeoff_landing_report',
            'airport' => 'KLAX',
            'planned_runway' => '25R',
            'planned_takeoff_weight' => ['amount' => 612400, 'unit' => 'lb'],
        ];
    }
}
