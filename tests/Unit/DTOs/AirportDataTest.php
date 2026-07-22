<?php

namespace Tests\Unit\DTOs;

use App\DTOs\AirportData;
use App\Services\Clients\AirportLookupClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AirportDataTest extends TestCase
{
    public function test_it_creates_an_airport_dto_from_a_partial_api_payload(): void
    {
        $airport = AirportData::fromApi([
            'icao' => 'EGLL',
            'iata' => 'LHR',
            'name' => 'Heathrow Airport',
            'city' => 'London',
        ]);

        $this->assertSame('EGLL', $airport->icao);
        $this->assertSame('LHR', $airport->iata);
        $this->assertSame('Heathrow Airport', $airport->name);
        $this->assertSame('London', $airport->city);
        $this->assertNull($airport->state);
        $this->assertSame('', $airport->country);
    }

    public function test_it_resolves_a_successful_lookup_response_to_a_dto(): void
    {
        Http::fake([
            'https://crewcompass.cc/api/v1/airports/lookup*' => Http::response([
                'data' => [
                    'icao' => 'EGLL',
                    'iata' => 'LHR',
                    'name' => 'Heathrow Airport',
                    'city' => 'London',
                    'state' => 'England',
                    'country' => 'United Kingdom',
                ],
            ], 200),
        ]);

        $client = new AirportLookupClient;
        $airport = $client->lookupByIata('lhr');

        $this->assertNotNull($airport);
        $this->assertSame('LHR', $airport->iata);
        $this->assertSame('United Kingdom', $airport->country);
    }
}
