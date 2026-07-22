<?php

namespace Tests\Feature;

use App\DTOs\Flight;
use App\DTOs\ParserResultData;
use App\Models\User;
use App\Services\Clients\AirportLookupClient;
use App\View\Models\Parser\ParserResultViewModel;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ParserResultComponentTest extends TestCase
{
    public function test_it_renders_the_export_button_in_the_header_without_helper_copy(): void
    {
        $html = Blade::render('<x-parser.result :model="$model" />', [
            'model' => ParserResultViewModel::fromData(ParserResultData::fromArray([
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
            ])),
        ]);

        $this->assertStringContainsString('Extracted Schedule', $html);
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

    public function test_it_renders_pre_enriched_flight_events_without_airport_lookups(): void
    {
        $airportLookupClient = $this->createMock(AirportLookupClient::class);
        $airportLookupClient->expects($this->never())->method('lookupByIata');

        $this->app->instance(AirportLookupClient::class, $airportLookupClient);

        $model = ParserResultViewModel::fromData(ParserResultData::fromArray([
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
                        'metadata' => [
                            'origin_airport_status' => 'found',
                            'origin_icao' => 'RKSI',
                            'origin_name' => 'Incheon International Airport',
                            'origin_city' => 'Seoul',
                            'origin_country' => 'South Korea',
                            'destination_airport_status' => 'found',
                            'destination_icao' => 'VHHH',
                            'destination_name' => 'Hong Kong International Airport',
                            'destination_city' => 'Hong Kong',
                            'destination_country' => 'Hong Kong',
                        ],
                    ]),
                ],
            ],
        ]));

        /** @var Flight $flight */
        $flight = $model->events[0];

        $this->assertSame('RKSI', $flight->metadata['origin_icao']);
        $this->assertSame('Incheon International Airport', $flight->metadata['origin_name']);
        $this->assertSame('Seoul', $flight->metadata['origin_city']);
        $this->assertSame('South Korea', $flight->metadata['origin_country']);
        $this->assertSame('VHHH', $flight->metadata['destination_icao']);
        $this->assertSame('Hong Kong International Airport', $flight->metadata['destination_name']);
        $this->assertSame('Hong Kong', $flight->metadata['destination_city']);
        $this->assertSame('Hong Kong', $flight->metadata['destination_country']);
    }

    private function makeResultModel(): ParserResultViewModel
    {
        return ParserResultViewModel::fromData(ParserResultData::fromArray([
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
        ]));
    }
}
