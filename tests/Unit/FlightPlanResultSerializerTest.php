<?php

namespace Tests\Unit;

use App\DTOs\AirportData;
use App\DTOs\CrewMemberData;
use App\DTOs\EnvelopeData;
use App\DTOs\Etops\EtopsCoordinateData;
use App\DTOs\Etops\EtopsData;
use App\DTOs\Etops\EtopsPointData;
use App\DTOs\FlightIdentityData;
use App\DTOs\FlightInitData;
use App\DTOs\FlightPlanData;
use App\DTOs\MaintenanceItemData;
use App\DTOs\MaintenanceLogData;
use App\DTOs\ParsedFlightPlanData;
use App\DTOs\RouteData;
use App\DTOs\ScheduleData;
use App\Enums\EtopsApplicability;
use App\Enums\MaintenanceItemType;
use App\Services\FlightPlan\Extractor\FlightRouteExtractor;
use App\Services\FlightPlan\FlightPlanResultSerializer;
use App\ValueObjects\AirportCode;
use App\ValueObjects\WeightQuantity;
use Tests\TestCase;

class FlightPlanResultSerializerTest extends TestCase
{
    public function test_it_preserves_the_flat_view_contract_and_adds_normalized_data(): void
    {
        $routeExtractor = $this->createMock(FlightRouteExtractor::class);
        $routeExtractor->expects($this->once())
            ->method('formatForIcaoDisplay')
            ->with('DCT TEST')
            ->willReturn("DCT\n TEST");
        $flightPlan = new FlightPlanData(
            identity: new FlightIdentityData(flightNumber: 'CKS256'),
            schedule: new ScheduleData,
            route: new RouteData(
                departure: new AirportCode('KLAX'),
                destination: new AirportCode('RKSI'),
                route: 'DCT TEST',
            ),
            maintenanceLog: new MaintenanceLogData(
                sectionPresent: true,
                etopsApplicability: EtopsApplicability::ConfirmedEtops,
                items: [
                    new MaintenanceItemData(
                        type: MaintenanceItemType::Mel,
                        number: '28-22-01',
                        description: 'Center tank override pump inoperative.',
                    ),
                ],
            ),
            envelope: new EnvelopeData(
                sectionPresent: true,
                sourceType: 'takeoff_landing_report',
                plannedTakeoffWeight: new WeightQuantity(612400, 'lb'),
            ),
            flightInit: new FlightInitData(sectionPresent: true, acarsInitDate: '11'),
            etops: new EtopsData(
                sectionPresent: true,
                applicability: EtopsApplicability::Unknown,
                entryPoint: new EtopsPointData(
                    'EENT',
                    new EtopsCoordinateData('N40 31.1', 'W131 22.6'),
                    0,
                ),
            ),
            crewMembers: [new CrewMemberData('Alex Morgan', 'CP', 'YIP', '4827')],
        );
        $parsed = new ParsedFlightPlanData(
            identity: [
                'flight_number' => 'CKS256',
                'trip_number' => null,
                'recall_number' => null,
                'aircraft_type' => null,
                'tail_number' => null,
                'flight_date' => null,
                'release_revision' => null,
            ],
            schedule: [
                'etd_utc' => null,
                'eta_utc' => null,
                'block_duration' => null,
                'report_time_utc' => null,
                'duty_end_utc' => null,
                'slot_times_utc' => [],
            ],
            route: [
                'departure' => 'KLAX',
                'destination' => 'RKSI',
                'alternate' => null,
                'route' => 'DCT TEST',
                'departure_runway' => null,
                'arrival_runway' => null,
                'departure_sid' => null,
                'arrival_star' => null,
                'distance_nautical_miles' => null,
            ],
            fuel: array_fill_keys([
                'ramp', 'taxi', 'takeoff', 'trip', 'contingency', 'alternate', 'final_reserve', 'estimated_landing',
            ], null),
            sourceFragments: [
                'fuel_summary' => 'must not leak',
                'maintenance_log' => 'private maintenance evidence',
                'envelope_takeoff_landing_report' => 'private TLR evidence',
            ],
            legacy: [
                'departure_airport' => new AirportData('KLAX', 'LAX', 'Los Angeles International', 'Los Angeles', 'California', 'United States'),
                'destination_airport' => null,
                'alternate_airport' => null,
                'etps' => [],
                'eent_coordinates' => null,
                'eexp_coordinates' => null,
                'initial_altitude' => 'FL 340',
                'duration' => '12h10m',
            ],
        );

        $result = (new FlightPlanResultSerializer($routeExtractor))->serialize($flightPlan, $parsed);

        $this->assertSame('KLAX', $result['departure']);
        $this->assertSame('Los Angeles International', $result['departure_airport']['name']);
        $this->assertSame("DCT\n TEST", $result['route']);
        $this->assertSame('CKS256', $result['flight_plan_data']['identity']['flightNumber']);
        $this->assertSame('28-22-01', $result['flight_plan_data']['maintenanceLog']['items'][0]['number']);
        $this->assertSame('Alex Morgan', $result['flight_plan_data']['crewMembers'][0]['name']);
        $this->assertSame('4827', $result['flight_plan_data']['crewMembers'][0]['employeeNumber']);
        $this->assertSame('11', $result['flight_plan_data']['flightInit']['acarsInitDate']);
        $this->assertSame(612400, $result['flight_plan_data']['envelope']['plannedTakeoffWeight']['amount']);
        $this->assertSame('N40 31.1', $result['flight_plan_data']['etops']['entryPoint']['coordinate']['latitude']);
        $this->assertArrayNotHasKey('crewMembers', $result['flight_plan_data']['maintenanceLog']);
        $this->assertArrayNotHasKey('source_fragments', $result);
        $this->assertStringNotContainsString('must not leak', json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('private maintenance evidence', json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('private TLR evidence', json_encode($result, JSON_THROW_ON_ERROR));
    }
}
