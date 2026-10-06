<?php

namespace Tests\Unit;

use App\DTOs\CrewManifestInputData;
use App\DTOs\CrewMemberData;
use App\DTOs\Maintenance\MaintenanceInputData;
use App\DTOs\WaypointData;
use App\Services\FlightPlan\CrewMemberDataBuilder;
use App\Services\FlightPlan\EtopsDataBuilder;
use App\Services\FlightPlan\MaintenanceLogDataBuilder;
use App\Services\FlightPlan\TakeoffLandingReportDataBuilder;
use App\Services\FlightPlan\WaypointDataBuilder;
use App\Services\FlightPlan\WeatherDataBuilder;
use Tests\TestCase;

class SubdomainDataBuilderTest extends TestCase
{
    public function test_extracted_subdomains_round_trip_through_their_serialized_hydrators(): void
    {
        $maintenanceBuilder = app(MaintenanceLogDataBuilder::class);
        $maintenance = $maintenanceBuilder->fromExtracted(MaintenanceInputData::fromExtracted([
            'section_present' => true,
            'items' => [[
                'type' => 'mel',
                'number' => '28-22-01',
                'description' => 'Fuel indication limitation',
            ]],
        ]));
        $this->assertSame($maintenance->toArray(), $maintenanceBuilder->fromSerialized($maintenance->toArray())?->toArray());

        $weatherBuilder = app(WeatherDataBuilder::class);
        $weather = $weatherBuilder->fromExtracted([
            'departure' => [
                'airport' => 'PANC',
                'metars' => ['METAR PANC TEST'],
                'tafs' => [],
            ],
            'raim' => 'RAIM AVAILABLE',
        ]);
        $this->assertNotNull($weather);
        $this->assertSame($weather->toArray(), $weatherBuilder->fromSerialized($weather->toArray())?->toArray());

        $crewBuilder = app(CrewMemberDataBuilder::class);
        $crew = $crewBuilder->fromExtracted(new CrewManifestInputData([[
            'name' => 'Alex Morgan',
            'role' => 'CP',
            'base' => 'YIP',
            'employee_number' => '4827',
            'high_mins' => true,
        ]]));
        $this->assertSame(
            array_map(static fn (CrewMemberData $member): array => $member->toArray(), $crew),
            array_map(static fn (CrewMemberData $member): array => $member->toArray(), $crewBuilder->fromSerialized(
                array_map(static fn (CrewMemberData $member): array => $member->toArray(), $crew),
            )),
        );

        $waypointBuilder = app(WaypointDataBuilder::class);
        $waypoints = $waypointBuilder->fromExtracted([[
            'identifier' => 'FIX01',
            'coordinate' => 'N40W130',
            'time' => '015',
            'total_time' => '01.15',
            'remaining_fuel' => '1477',
        ]], ['ramp' => ['amount' => 216800.0, 'unit' => 'lb']]);
        $serializedWaypoints = array_map(static fn (WaypointData $waypoint): array => $waypoint->toArray(), $waypoints);
        $this->assertSame(
            $serializedWaypoints,
            array_map(static fn (WaypointData $waypoint): array => $waypoint->toArray(), $waypointBuilder->fromSerialized($serializedWaypoints)),
        );

        $tlrBuilder = app(TakeoffLandingReportDataBuilder::class);
        $tlr = $tlrBuilder->fromExtracted([
            'section_present' => true,
            'source_type' => 'takeoff_landing_report',
            'airport' => 'PANC',
            'planned_takeoff_weight' => ['amount' => 612400, 'unit' => 'lb'],
        ]);
        $this->assertNotNull($tlr);
        $this->assertSame($tlr->toArray(), $tlrBuilder->fromSerialized($tlr->toArray())?->toArray());

        $etopsBuilder = app(EtopsDataBuilder::class);
        $etops = $etopsBuilder->fromExtracted([
            'section_present' => true,
            'applicability' => 'confirmed_etops',
            'rating_minutes' => 180,
            'eent_coordinates' => 'N40 31.1 W131 22.6',
            'eexp_coordinates' => 'N45 19.3 E151 36.4',
            'etps' => [[
                'label' => 'ETP1',
                'airports' => 'KSFO-PACD',
                'coordinates' => 'N45 43.7 W143 53.1',
                'scenario' => 'ALL ENGINE',
            ]],
        ]);
        $this->assertNotNull($etops);
        $this->assertSame($etops->toArray(), $etopsBuilder->fromSerialized($etops->toArray())?->toArray());
    }

    public function test_only_phase_markers_can_round_trip_without_coordinates(): void
    {
        $builder = app(WaypointDataBuilder::class);
        $source = [
            ['identifier' => 'TOC', 'coordinate' => null, 'time' => '002', 'total_time' => '00.15', 'remaining_fuel' => '1741', 'tbo' => '0119'],
            ['identifier' => 'TOD', 'coordinate' => '', 'time' => null, 'total_time' => null, 'remaining_fuel' => null],
            ['identifier' => 'FIX01', 'coordinate' => null, 'kind' => 'toc'],
            ['identifier' => '-EDWW', 'coordinate' => null, 'kind' => 'fir'],
            ['identifier' => '', 'coordinate' => null],
        ];

        $waypoints = $builder->fromExtracted($source, ['takeoff' => ['amount' => 186000.0, 'unit' => 'lb']]);
        $serialized = json_decode(json_encode($waypoints, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['TOC', 'TOD'], array_column($serialized, 'identifier'));
        $this->assertSame(['toc', 'tod'], array_column($serialized, 'kind'));
        $this->assertSame([null, null], array_column($serialized, 'coordinate'));
        $this->assertSame(2, $serialized[0]['legDurationMinutes']);
        $this->assertSame(15, $serialized[0]['cumulativeDurationMinutes']);
        $this->assertSame(['amount' => 174100, 'unit' => 'lb'], $serialized[0]['remainingFuel']);
        $this->assertSame('0119', $serialized[0]['tbo']);
        $this->assertNull($serialized[1]['cumulativeDurationMinutes']);
        $this->assertNull($serialized[1]['remainingFuel']);

        $rehydrated = $builder->fromSerialized([
            ...$serialized,
            ['identifier' => 'FIX01', 'kind' => 'toc'],
            ['identifier' => '-EDWW', 'kind' => 'fir'],
        ]);
        $this->assertSame($serialized, json_decode(json_encode($rehydrated, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
    }
}
