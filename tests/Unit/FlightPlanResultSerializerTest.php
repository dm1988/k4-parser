<?php

namespace Tests\Unit;

use App\DTOs\AirportData;
use App\DTOs\CrewMemberData;
use App\DTOs\Etops\EtopsCoordinateData;
use App\DTOs\Etops\EtopsData;
use App\DTOs\Etops\EtopsPointData;
use App\DTOs\FlightIdentityData;
use App\DTOs\FlightInitData;
use App\DTOs\FlightPlanData;
use App\DTOs\GeneralDeclarationData;
use App\DTOs\Maintenance\MaintenanceItemData;
use App\DTOs\Maintenance\MaintenanceLogData;
use App\DTOs\ReleaseAuthorizationData;
use App\DTOs\RouteData;
use App\DTOs\ScheduleData;
use App\DTOs\TakeoffLandingReportData;
use App\DTOs\WaypointData;
use App\Enums\EtopsApplicability;
use App\Enums\MaintenanceItemType;
use App\Enums\OperationsSpecification;
use App\Services\FlightPlan\FlightPlanResultSerializer;
use App\ValueObjects\AirportCode;
use App\ValueObjects\FuelQuantity;
use App\ValueObjects\WeightQuantity;
use PHPUnit\Framework\TestCase;

class FlightPlanResultSerializerTest extends TestCase
{
    public function test_it_serializes_only_the_normalized_public_contract(): void
    {
        $flightPlan = new FlightPlanData(
            identity: new FlightIdentityData(flightNumber: 'CKS256'),
            schedule: new ScheduleData(blockDuration: '12h10m'),
            route: new RouteData(
                departure: new AirportCode('KLAX'),
                destination: new AirportCode('RKSI'),
                departureAirport: new AirportData(
                    'KLAX',
                    'LAX',
                    'Los Angeles International',
                    'Los Angeles',
                    'California',
                    'United States',
                ),
                route: 'DCT TEST',
            ),
            maintenanceLog: new MaintenanceLogData(
                sectionPresent: true,
                items: [
                    new MaintenanceItemData(
                        type: MaintenanceItemType::Mel,
                        number: '28-22-01',
                        description: 'Center tank override pump inoperative.',
                    ),
                ],
            ),
            takeoffLandingReport: new TakeoffLandingReportData(
                sectionPresent: true,
                sourceType: 'takeoff_landing_report',
                plannedTakeoffWeight: new WeightQuantity(612400, 'lb'),
            ),
            flightInit: new FlightInitData(sectionPresent: true, acarsInitDate: '11'),
            etops: new EtopsData(
                sectionPresent: true,
                applicability: EtopsApplicability::Unknown,
                ratingMinutes: 180,
                entryPoint: new EtopsPointData(
                    'EENT',
                    new EtopsCoordinateData('N40 31.1', 'W131 22.6'),
                    0,
                ),
            ),
            crewMembers: [new CrewMemberData('Alex Morgan', 'CP', 'YIP', '4827', true)],
            waypoints: [new WaypointData('FIX01', 'N01 02.3 E004 05.6', 5, 11, FuelQuantity::pounds(0))],
            generalDeclaration: new GeneralDeclarationData(true),
            releaseAuthorization: new ReleaseAuthorizationData(OperationsSpecification::B44),
        );

        $result = (new FlightPlanResultSerializer)->serialize($flightPlan);

        $this->assertSame(['flight_plan_data'], array_keys($result));
        $this->assertSame('CKS256', $result['flight_plan_data']['identity']['flightNumber']);
        $this->assertSame('Los Angeles International', $result['flight_plan_data']['route']['departureAirport']['name']);
        $this->assertSame('12h10m', $result['flight_plan_data']['schedule']['blockDuration']);
        $this->assertSame('28-22-01', $result['flight_plan_data']['maintenanceLog']['items'][0]['number']);
        $this->assertSame('Alex Morgan', $result['flight_plan_data']['crewMembers'][0]['name']);
        $this->assertSame('4827', $result['flight_plan_data']['crewMembers'][0]['employeeNumber']);
        $this->assertTrue($result['flight_plan_data']['crewMembers'][0]['highMins']);
        $this->assertSame('11', $result['flight_plan_data']['flightInit']['acarsInitDate']);
        $this->assertSame(612400, $result['flight_plan_data']['takeoffLandingReport']['plannedTakeoffWeight']['amount']);
        $this->assertSame(
            $result['flight_plan_data']['takeoffLandingReport'],
            $result['flight_plan_data']['envelope'],
        );
        $this->assertSame('N40 31.1', $result['flight_plan_data']['etops']['entryPoint']['coordinate']['latitude']);
        $this->assertSame(180, $result['flight_plan_data']['etops']['ratingMinutes']);
        $this->assertSame('FIX01', $result['flight_plan_data']['waypoints'][0]['identifier']);
        $this->assertSame(0.0, $result['flight_plan_data']['waypoints'][0]['remainingFuel']['amount']);
        $this->assertTrue($result['flight_plan_data']['generalDeclaration']['sectionPresent']);
        $this->assertSame('b44', $result['flight_plan_data']['releaseAuthorization']['operationsSpecification']);
        $this->assertArrayNotHasKey('crewMembers', $result['flight_plan_data']['maintenanceLog']);
    }
}
