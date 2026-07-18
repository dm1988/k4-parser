<?php

namespace Tests\Unit;

use App\Services\AirportLookupClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AirportLookupClientTest extends TestCase
{
    public function test_successful_lookups_return_airport_data(): void
    {
        Http::preventStrayRequests();
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
            ]),
        ]);

        $airport = app(AirportLookupClient::class)->lookupByIata('lhr');

        $this->assertNotNull($airport);
        $this->assertSame('LHR', $airport->iata);
        $this->assertSame('EGLL', $airport->icao);
        Http::assertSentCount(1);
    }

    public function test_connection_failures_are_retried_and_return_null(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::failedConnection('Connection timed out.')]);
        Log::spy();

        $airport = app(AirportLookupClient::class)->lookupByIcao('EGLL');

        $this->assertNull($airport);
        Http::assertSentCount(3);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Airport lookup provider connection failed after retries.'
                && $context['lookup_type'] === 'icao'
                && $context['lookup_code'] === 'EGLL');
    }

    public function test_not_found_responses_are_not_retried(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(status: 404)]);

        $this->assertNull(app(AirportLookupClient::class)->lookupByIata('LHR'));
        Http::assertSentCount(1);
    }

    public function test_unprocessable_responses_are_not_retried(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(status: 422)]);
        Log::spy();

        $this->assertNull(app(AirportLookupClient::class)->lookupByIata('LHR'));
        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_temporary_upstream_failures_are_retried_until_successful(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::sequence()
                ->pushStatus(503)
                ->pushStatus(500)
                ->push([
                    'data' => [
                        'icao' => 'EGLL',
                        'iata' => 'LHR',
                        'name' => 'Heathrow Airport',
                        'city' => 'London',
                        'state' => 'England',
                        'country' => 'United Kingdom',
                    ],
                ]),
        ]);

        $airport = app(AirportLookupClient::class)->lookupByIata('LHR');

        $this->assertNotNull($airport);
        $this->assertSame('LHR', $airport->iata);
        Http::assertSentCount(3);
    }

    public function test_rate_limit_responses_are_bounded_to_three_attempts(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(status: 429)]);
        Log::spy();

        $this->assertNull(app(AirportLookupClient::class)->lookupByIata('LHR'));
        Http::assertSentCount(3);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Airport lookup provider remained unavailable after retries.'
                && $context['status'] === 429);
    }
}
