<?php

namespace Tests\Unit;

use App\DTOs\AirportData;
use App\DTOs\Flight;
use App\Services\AirportEnrichmentService;
use App\Services\AirportLookupClient;
use Tests\TestCase;

class AirportEnrichmentServiceTest extends TestCase
{
    public function test_it_resolves_each_unique_code_once_and_attaches_serializable_metadata(): void
    {
        $client = $this->createMock(AirportLookupClient::class);
        $client->expects($this->exactly(2))
            ->method('lookupByIataOrFail')
            ->willReturnCallback(static fn (string $code): ?AirportData => match ($code) {
                'AUS' => new AirportData('KAUS', 'AUS', 'Austin Airport', 'Austin', 'Texas', 'United States'),
                'HKG' => null,
            });
        $this->app->instance(AirportLookupClient::class, $client);

        $flight = Flight::fromArray([
            'title' => 'AUS-HKG',
            'type' => 'flight',
            'origin' => 'aus',
            'destination' => 'HKG',
        ]);
        $parsed = app(AirportEnrichmentService::class)->enrich([
            'calendar_events' => [$flight, $flight],
        ]);

        /** @var Flight $enriched */
        $enriched = $parsed['calendar_events'][0];
        $this->assertSame('found', $enriched->metadata['origin_airport_status']);
        $this->assertSame('KAUS', $enriched->metadata['origin_icao']);
        $this->assertSame('missing', $enriched->metadata['destination_airport_status']);
        $this->assertArrayNotHasKey('destination_name', $enriched->metadata);
    }
}
