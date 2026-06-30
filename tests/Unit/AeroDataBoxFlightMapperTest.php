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
        $payload['departure']['scheduledTime']['utc'] = '2026-06-30T18:00:00Z';
        $reformattedFlight = $mapper->map($payload, 'N772CK');
        $payload['arrival']['revisedTime']['utc'] = '2026-07-01 03:30Z';
        $revisedFlight = $mapper->map($payload, 'N772CK');

        $this->assertNotNull($flight);
        $this->assertNotNull($reformattedFlight);
        $this->assertNotNull($revisedFlight);
        $this->assertSame($flight->externalId, $reformattedFlight->externalId);
        $this->assertSame($flight->externalId, $revisedFlight->externalId);
        $this->assertSame('N772CK', $flight->tailNumber);
        $this->assertSame('K4 304', $flight->flightNumber);
        $this->assertSame('NRT', $flight->origin);
        $this->assertSame('JFK', $flight->destination);
        $this->assertSame('En Route', $flight->status);
        $this->assertSame('2026-06-30T18:15:00+00:00', $flight->start->toIso8601String());
        $this->assertSame('2026-07-01T03:00:00+00:00', $flight->end->toIso8601String());
        $this->assertSame([
            'source' => 'aerodatabox',
            'external_id' => $flight->externalId,
            'aircraft_id' => 42,
        ], array_intersect_key(
            $flight->toFlightEventAttributes(42),
            array_flip(['source', 'external_id', 'aircraft_id']),
        ));
    }

    public function test_it_rejects_a_flight_without_required_schedule_data(): void
    {
        $payload = $this->flightPayload();
        unset($payload['departure']['scheduledTime']);

        $this->assertNull((new AeroDataBoxFlightMapper)->map($payload, 'N772CK'));
    }

    public function test_it_prefers_provider_flight_id_when_available(): void
    {
        $payload = $this->flightPayload();
        $payload['flightId'] = 'adb-123';
        $flight = (new AeroDataBoxFlightMapper)->map($payload, 'N772CK');

        unset($payload['departure']['scheduledTime']);
        $updatedFlight = (new AeroDataBoxFlightMapper)->map($payload, 'N772CK');

        $this->assertNotNull($flight);
        $this->assertNotNull($updatedFlight);
        $this->assertSame($flight->externalId, $updatedFlight->externalId);
        $this->assertSame('adb-123', $updatedFlight->metadata['provider_flight_id']);
    }

    public function test_it_preserves_a_flight_with_an_inconsistent_live_timeline(): void
    {
        $payload = $this->flightPayload();
        $payload['departure']['actualTime']['utc'] = '2026-07-01 04:00Z';
        $payload['arrival']['revisedTime']['utc'] = '2026-07-01 03:00Z';

        $flight = (new AeroDataBoxFlightMapper)->map($payload, 'N772CK');

        $this->assertNotNull($flight);
        $this->assertSame('2026-07-01T04:00:00+00:00', $flight->start->toIso8601String());
        $this->assertSame('2026-07-01T04:01:00+00:00', $flight->end->toIso8601String());
        $this->assertTrue($flight->metadata['timeline_anomaly']);
        $this->assertSame('2026-07-01T03:00:00+00:00', $flight->metadata['reported_end_utc']);
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
