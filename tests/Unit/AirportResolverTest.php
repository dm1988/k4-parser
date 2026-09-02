<?php

namespace Tests\Unit;

use App\DTOs\AirportData;
use App\Services\Clients\AirportLookupClient;
use App\Services\Schedule\AirportResolver;
use RuntimeException;
use Tests\TestCase;

class AirportResolverTest extends TestCase
{
    public function test_it_resolves_four_letter_icao_codes(): void
    {
        $airport = new AirportData(
            'PANC',
            'ANC',
            'Ted Stevens Anchorage International Airport',
            'Anchorage',
            'Alaska',
            'United States',
        );
        $client = $this->createMock(AirportLookupClient::class);
        $client->expects($this->never())->method('lookupByIataOrFail');
        $client->expects($this->once())
            ->method('lookupByIcaoOrFail')
            ->with('PANC')
            ->willReturn($airport);

        $resolved = app(AirportResolver::class, ['client' => $client])
            ->resolveMany([' panc ', 'PANC']);

        $this->assertSame(['PANC'], array_keys($resolved));
        $this->assertTrue($resolved['PANC']->wasFound());
        $this->assertSame($airport, $resolved['PANC']->airport);
    }

    public function test_it_normalizes_deduplicates_caches_and_isolates_failures(): void
    {
        $client = $this->createMock(AirportLookupClient::class);
        $client->expects($this->exactly(3))
            ->method('lookupByIataOrFail')
            ->willReturnCallback(static fn (string $code): ?AirportData => match ($code) {
                'AUS' => new AirportData('KAUS', 'AUS', 'Austin Airport', 'Austin', 'Texas', 'United States'),
                'HKG' => null,
                'YYZ' => throw new RuntimeException('Provider unavailable'),
                default => throw new RuntimeException("Unexpected airport code: {$code}"),
            });

        $resolved = app(AirportResolver::class, ['client' => $client])
            ->resolveMany([' aus ', 'AUS', 'bad-code', '', 'HKG', 'YYZ']);

        $this->assertSame(['AUS', 'HKG', 'YYZ'], array_keys($resolved));
        $this->assertTrue($resolved['AUS']->wasFound());
        $this->assertTrue($resolved['HKG']->isMissing());
        $this->assertTrue($resolved['YYZ']->isUnavailable());

        $cachedClient = $this->createMock(AirportLookupClient::class);
        $cachedClient->expects($this->never())->method('lookupByIataOrFail');
        $cached = app(AirportResolver::class, ['client' => $cachedClient])
            ->resolveMany(['AUS', 'HKG', 'YYZ']);

        $this->assertTrue($cached['AUS']->wasFound());
        $this->assertTrue($cached['HKG']->isMissing());
        $this->assertTrue($cached['YYZ']->isUnavailable());
    }
}
