<?php

namespace Tests\Unit\Actions\Schedule;

use App\Actions\Schedule\BuildScheduleResult;
use App\DTOs\DutyEvent;
use App\DTOs\ExtractedResultData;
use App\DTOs\Flight;
use Tests\TestCase;

class BuildScheduleResultTest extends TestCase
{
    public function test_it_assigns_download_ids_to_supported_event_payloads(): void
    {
        $result = app(BuildScheduleResult::class)->handle(
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

        $events = $result->parsed['calendar_events'];

        $this->assertInstanceOf(ExtractedResultData::class, $result);
        $this->assertIsString($result->parseKey);
        $this->assertNotSame('', $result->parseKey);
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
