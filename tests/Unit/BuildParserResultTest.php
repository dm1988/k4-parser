<?php

namespace Tests\Unit;

use App\Actions\BuildParserResult;
use App\DTOs\DutyEvent;
use App\DTOs\Flight;
use Tests\TestCase;

class BuildParserResultTest extends TestCase
{
    public function test_it_assigns_download_ids_to_supported_event_payloads(): void
    {
        $result = app(BuildParserResult::class)->handle(
            type: 'roster',
            source: 'text',
            documentType: null,
            parsed: [
                'trip' => ['trip_number' => '13131'],
                'calendar_events' => [
                    Flight::fromArray([
                        'title' => 'CKS 240 ICN-HKG',
                        'type' => 'flight',
                        'download_url' => '',
                    ]),
                    DutyEvent::fromArray([
                        'title' => 'Hotel Check-In',
                        'type' => 'duty',
                        'download_url' => '',
                    ]),
                    [
                        'title' => 'Array Event',
                        'type' => 'duty',
                    ],
                ],
            ],
        );

        $events = $result['parsed']['calendar_events'];

        $this->assertIsString($result['parse_key']);
        $this->assertNotSame('', $result['parse_key']);
        $this->assertInstanceOf(Flight::class, $events[0]);
        $this->assertIsString($events[0]->downloadId);
        $this->assertNotSame('', $events[0]->downloadId);
        $this->assertInstanceOf(DutyEvent::class, $events[1]);
        $this->assertIsString($events[1]->downloadId);
        $this->assertNotSame('', $events[1]->downloadId);
        $this->assertIsArray($events[2]);
        $this->assertIsString($events[2]['download_id']);
        $this->assertNotSame('', $events[2]['download_id']);
    }
}
