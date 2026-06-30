<?php

namespace Tests\Unit;

use App\Mappers\AeroDataBoxFlightMapper;
use PHPUnit\Framework\TestCase;

class AeroDataBoxFlightMapperTest extends TestCase
{
    public function test_it_maps_a_flight_and_keeps_identity_stable_when_revised_times_change(): void
    {
        $mapper = new AeroDataBoxFlightMapper;
        $payload = $this->flightPayload();

        $flight = $mapper->map($payload, 'N772CK');
        $payload['arrival']['revisedTime']['utc'] = '2026-07-01 03:30Z';
        $revisedFlight = $mapper->map($payload, 'N772CK');

        $this->assertNotNull($flight);
        $this->assertNotNull($revisedFlight);
        $this->assertSame($flight->externalId, $revisedFlight->externalId);
        $this->assertSame('N772CK', $flight->tailNumber);
        $this->assertSame('K4 304', $flight->flightNumber);
        $this->assertSame('NRT', $flight->origin);
        $this->assertSame('JFK', $flight->destination);
        $this->assertSame('En Route', $flight->status);
        $this->assertSame('2026-06-30T18:15:00+00:00', $flight->start->toIso8601String());
        $this->assertSame('2026-07-01T03:00:00+00:00', $flight->end->toIso8601String());
    }

    public function test_it_rejects_a_flight_without_required_schedule_data(): void
    {
        $payload = $this->flightPayload();
        unset($payload['departure']['scheduledTime']);

        $this->assertNull((new AeroDataBoxFlightMapper)->map($payload, 'N772CK'));
    }

    /** @return array<string, mixed> */
    private function flightPayload(): array
    {
        return [
            'number' => 'K4 304',
            'callSign' => 'CKS304',
            'status' => 'EnRoute',
            'isCargo' => true,
            'lastUpdatedUtc' => '2026-06-30 19:00Z',
            'aircraft' => ['reg' => 'N772CK', 'model' => 'Boeing 777-F'],
            'departure' => [
                'airport' => ['iata' => 'NRT', 'icao' => 'RJAA'],
                'scheduledTime' => ['utc' => '2026-06-30 18:00Z'],
                'actualTime' => ['utc' => '2026-06-30 18:15Z'],
            ],
            'arrival' => [
                'airport' => ['iata' => 'JFK', 'icao' => 'KJFK'],
                'scheduledTime' => ['utc' => '2026-07-01 02:45Z'],
                'revisedTime' => ['utc' => '2026-07-01 03:00Z'],
            ],
        ];
    }
}
