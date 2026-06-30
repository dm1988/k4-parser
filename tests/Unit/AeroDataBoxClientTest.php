<?php

namespace Tests\Unit;

use App\Services\AeroDataBoxClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AeroDataBoxClientTest extends TestCase
{
    public function test_it_requests_registration_history_with_api_market_authentication(): void
    {
        config()->set('services.aerodatabox.base_url', 'https://prod.api.market/api/v1/aedbx/aerodatabox');
        config()->set('services.aerodatabox.key', 'secret-key');
        config()->set('services.aerodatabox.throttle_ms', 0);
        Http::fake([
            '*' => Http::response([[
                'number' => 'K4 304',
                'aircraft' => ['reg' => 'N772CK'],
                'departure' => [
                    'scheduledTime' => ['utc' => '2026-06-30 18:00Z'],
                    'airport' => ['iata' => 'NRT'],
                ],
                'arrival' => ['airport' => ['iata' => 'JFK']],
            ]], 200),
        ]);

        $flights = (new AeroDataBoxClient)->flightsByRegistration(
            'n772ck',
            CarbonImmutable::parse('2026-06-29 UTC'),
            CarbonImmutable::parse('2026-07-02 UTC'),
        );

        $this->assertCount(1, $flights);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => str_starts_with(
            $request->url(),
            'https://prod.api.market/api/v1/aedbx/aerodatabox/flights/Reg/N772CK/',
        ) && $request->hasHeader('x-magicapi-key', 'secret-key'));
        Http::assertSent(fn (Request $request): bool => $request->url()
            === 'https://prod.api.market/api/v1/aedbx/aerodatabox/flights/Reg/N772CK/2026-06-29/2026-06-30?dateLocalRole=Both');
        Http::assertSent(fn (Request $request): bool => $request->url()
            === 'https://prod.api.market/api/v1/aedbx/aerodatabox/flights/Reg/N772CK/2026-07-01/2026-07-02?dateLocalRole=Both');
    }

    #[DataProvider('emptyFlightResponseStatuses')]
    public function test_it_treats_no_flight_responses_as_an_empty_result(int $status): void
    {
        config()->set('services.aerodatabox.base_url', 'https://prod.api.market/api/v1/aedbx/aerodatabox');
        config()->set('services.aerodatabox.key', 'secret-key');
        Http::fake(['*' => Http::response(null, $status)]);

        $flights = (new AeroDataBoxClient)->flightsByRegistration(
            'N772CK',
            CarbonImmutable::parse('2026-06-29 UTC'),
            CarbonImmutable::parse('2026-06-30 UTC'),
        );

        $this->assertSame([], $flights);
    }

    /** @return array<string, array{int}> */
    public static function emptyFlightResponseStatuses(): array
    {
        return [
            'no content' => [204],
            'not found' => [404],
        ];
    }

    public function test_it_deduplicates_flights_with_fallback_identity_fields(): void
    {
        config()->set('services.aerodatabox.base_url', 'https://prod.api.market/api/v1/aedbx/aerodatabox');
        config()->set('services.aerodatabox.key', 'secret-key');
        config()->set('services.aerodatabox.throttle_ms', 0);
        $flight = [
            'aircraft' => ['reg' => 'N772CK'],
            'departure' => [
                'actualTime' => ['utc' => '2026-06-30 18:00Z'],
                'airport' => ['icao' => 'RJAA'],
            ],
            'arrival' => ['airport' => ['icao' => 'KJFK']],
        ];
        Http::fake(['*' => Http::response([$flight], 200)]);

        $flights = (new AeroDataBoxClient)->flightsByRegistration(
            'N772CK',
            CarbonImmutable::parse('2026-06-29 UTC'),
            CarbonImmutable::parse('2026-07-01 UTC'),
        );

        $this->assertSame([$flight], $flights);
        Http::assertSentCount(2);
    }
}
