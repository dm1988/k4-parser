<?php

namespace Tests\Unit\DTOs\Etops;

use App\DTOs\Etops\EtopsAlternateData;
use App\DTOs\Etops\EtopsCoordinateData;
use App\DTOs\Etops\EtopsCriticalFuelData;
use App\DTOs\Etops\EtopsData;
use App\DTOs\Etops\EtopsDiversionData;
use App\DTOs\Etops\EtopsEqualTimePointData;
use App\DTOs\Etops\EtopsPointData;
use App\DTOs\Etops\EtopsScenarioData;
use App\Enums\EtopsApplicability;
use App\ValueObjects\AirportCode;
use App\ValueObjects\FuelQuantity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EtopsDataTest extends TestCase
{
    public function test_it_serializes_typed_etops_operational_data_in_source_order(): void
    {
        $entry = new EtopsPointData('EENT', new EtopsCoordinateData('n40  31.1', 'w131 22.6'), 1);
        $exit = new EtopsPointData('EEXP', new EtopsCoordinateData('N45 19.3', 'E151 36.4'), 4);
        $firstEtp = new EtopsEqualTimePointData(
            label: 'ETP1',
            coordinate: new EtopsCoordinateData('N45 43.7', 'W143 53.1'),
            sequence: 2,
            firstAlternate: new AirportCode('KSFO'),
            secondAlternate: new AirportCode('PACD'),
        );
        $secondEtp = new EtopsEqualTimePointData('ETP1', new EtopsCoordinateData('N46 00.0', 'W144 00.0'), 3);
        $scenario = new EtopsScenarioData(
            name: 'All engine decompression',
            equalTimePointLabel: 'ETP1',
            diversion: new EtopsDiversionData(
                alternate: new AirportCode('PACD'),
                timeMinutes: 189,
                distanceNauticalMiles: 1240,
                flightLevel: 100,
            ),
            criticalFuel: new EtopsCriticalFuelData(FuelQuantity::pounds(42500), 'LRC'),
            remarks: ' Source listed scenario ',
        );

        $data = new EtopsData(
            sectionPresent: true,
            applicability: EtopsApplicability::ConfirmedEtops,
            entryPoint: $entry,
            exitPoint: $exit,
            equalTimePoints: [$firstEtp, $secondEtp],
            alternates: [new EtopsAlternateData(new AirportCode('PACD'))],
            scenarios: [$scenario],
        );

        $serialized = $data->toArray();

        $this->assertSame('confirmed_etops', $serialized['applicability']);
        $this->assertSame(['latitude' => 'N40 31.1', 'longitude' => 'W131 22.6'], $serialized['entryPoint']['coordinate']);
        $this->assertSame(['ETP1', 'ETP1'], array_column($serialized['equalTimePoints'], 'label'));
        $this->assertSame([2, 3], array_column($serialized['equalTimePoints'], 'sequence'));
        $this->assertSame('PACD', $serialized['scenarios'][0]['diversion']['alternate']);
        $this->assertSame(['amount' => 42500.0, 'unit' => 'lb'], $serialized['scenarios'][0]['criticalFuel']['quantity']);
        $this->assertSame('Source listed scenario', $serialized['scenarios'][0]['remarks']);
        $this->assertSame($serialized, $data->jsonSerialize());
    }

    public function test_it_represents_an_absent_non_etops_section_without_inventing_points(): void
    {
        $data = new EtopsData(false, EtopsApplicability::ConfirmedNonEtops);

        $this->assertFalse($data->sectionPresent);
        $this->assertSame([], $data->equalTimePoints);
        $this->assertSame([], $data->alternates);
        $this->assertSame([], $data->scenarios);
        $this->assertNull($data->entryPoint);
        $this->assertNull($data->exitPoint);
    }

    public function test_coordinates_reject_invalid_degrees_minutes_and_poles(): void
    {
        $invalidCoordinates = [
            ['40 31.1', 'W131 22.6'],
            ['N91 00.0', 'W131 22.6'],
            ['N40 60.0', 'W131 22.6'],
            ['N90 00.1', 'W131 22.6'],
            ['N40 31.1', 'W181 00.0'],
            ['N40 31.1', 'W180 00.1'],
        ];

        foreach ($invalidCoordinates as [$latitude, $longitude]) {
            try {
                new EtopsCoordinateData($latitude, $longitude);
                $this->fail("Expected invalid ETOPS coordinate {$latitude} {$longitude} to be rejected.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_diversion_values_and_point_sequence_must_not_be_negative(): void
    {
        try {
            new EtopsDiversionData(new AirportCode('PACD'), timeMinutes: -1);
            $this->fail('Expected a negative diversion time to be rejected.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);

        new EtopsPointData('EENT', new EtopsCoordinateData('N40 31.1', 'W131 22.6'), -1);
    }
}
