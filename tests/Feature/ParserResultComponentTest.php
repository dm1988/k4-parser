<?php

namespace Tests\Feature;

use App\DTOs\AirportData;
use App\DTOs\Flight;
use App\Models\User;
use App\Services\AirportLookupClient;
use App\View\Models\Parser\ParserResultViewModel;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ParserResultComponentTest extends TestCase
{
    public function test_it_renders_the_export_button_in_the_header_without_helper_copy(): void
    {
        $html = Blade::render('<x-parser.result :model="$model" />', [
            'model' => ParserResultViewModel::fromArray([
                'source' => 'text',
                'parse_key' => '01KXK47PE0HNRXE4VV2N8K8N58',
                'filters' => [],
                'parsed' => [
                    'trip' => [
                        'trip_number' => '13131',
                    ],
                    'calendar_events' => [
                        [
                            'type' => 'layover',
                            'title' => 'Layover ICN',
                            'start' => '2026-07-02T23:59:00+00:00',
                            'end' => '2026-07-04T09:00:00+00:00',
                            'metadata' => [],
                            'download_id' => '01JTESTEVENTKEYABC123',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->assertStringContainsString('Parsed Output', $html);
        $this->assertStringContainsString('Download all (.ics)', $html);
        $this->assertStringNotContainsString('Download the parsed events as a calendar file.', $html);
    }

    public function test_it_hides_raw_json_from_normal_users(): void
    {
        $this->actingAs(User::factory()->make([
            'role' => 'user',
        ]));

        $html = Blade::render('<x-parser.result :model="$model" />', [
            'model' => $this->makeResultModel(),
        ]);

        $this->assertStringNotContainsString('Raw JSON', $html);
    }

    public function test_it_shows_raw_json_to_admin_users(): void
    {
        $this->actingAs(User::factory()->make([
            'role' => 'admin',
        ]));

        $html = Blade::render('<x-parser.result :model="$model" />', [
            'model' => $this->makeResultModel(),
        ]);

        $this->assertStringContainsString('Raw JSON', $html);
    }

    public function test_it_enriches_flight_events_with_airport_details_from_iata_lookups(): void
    {
        $airportLookupClient = $this->createMock(AirportLookupClient::class);
        $airportLookupClient->expects($this->exactly(2))
            ->method('lookupByIata')
            ->willReturnCallback(static fn (string $iata): ?AirportData => match ($iata) {
                'ICN' => new AirportData(
                    icao: 'RKSI',
                    iata: 'ICN',
                    name: 'Incheon International Airport',
                    city: 'Seoul',
                    state: null,
                    country: 'South Korea',
                ),
                'HKG' => new AirportData(
                    icao: 'VHHH',
                    iata: 'HKG',
                    name: 'Hong Kong International Airport',
                    city: 'Hong Kong',
                    state: null,
                    country: 'Hong Kong',
                ),
                default => null,
            });

        $this->app->instance(AirportLookupClient::class, $airportLookupClient);

        $model = ParserResultViewModel::fromArray([
            'source' => 'text',
            'parse_key' => '01KXK47PE0HNRXE4VV2N8K8N58',
            'filters' => [],
            'parsed' => [
                'trip' => [
                    'trip_number' => '13131',
                ],
                'calendar_events' => [
                    Flight::fromArray([
                        'title' => 'CKS 240 ICN-HKG',
                        'type' => 'flight',
                        'typeLabel' => 'Flight',
                        'typeDescription' => 'Scheduled flying segment.',
                        'scheduleLabel' => 'Jun 15, 11:45 PM -> Jun 16, 3:45 AM',
                        'durationLabel' => '4:00h',
                        'badgeColor' => 'bg-blue-100 text-blue-900',
                        'downloadUrl' => 'https://example.test/export',
                        'downloadId' => '01JTESTEVENTKEYABC123',
                        'flightNumber' => 'CKS 240',
                        'origin' => 'ICN',
                        'destination' => 'HKG',
                    ]),
                ],
            ],
        ]);

        /** @var Flight $flight */
        $flight = $model->events[0];

        $this->assertSame('RKSI', $flight->metadata['origin_icao']);
        $this->assertSame('Incheon International Airport', $flight->metadata['origin_name']);
        $this->assertSame('Seoul', $flight->metadata['origin_city']);
        $this->assertSame('VHHH', $flight->metadata['destination_icao']);
        $this->assertSame('Hong Kong International Airport', $flight->metadata['destination_name']);
        $this->assertSame('Hong Kong', $flight->metadata['destination_city']);
    }

    private function makeResultModel(): ParserResultViewModel
    {
        return ParserResultViewModel::fromArray([
            'source' => 'text',
            'parse_key' => '01KXK47PE0HNRXE4VV2N8K8N58',
            'filters' => [],
            'parsed' => [
                'trip' => [
                    'trip_number' => '13131',
                ],
                'calendar_events' => [
                    [
                        'type' => 'layover',
                        'title' => 'Layover ICN',
                        'start' => '2026-07-02T23:59:00+00:00',
                        'end' => '2026-07-04T09:00:00+00:00',
                        'metadata' => [],
                        'download_id' => '01JTESTEVENTKEYABC123',
                    ],
                ],
            ],
        ]);
    }
}
