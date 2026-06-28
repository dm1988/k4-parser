<?php

namespace Tests\Unit;

use App\Services\IcsCalendarService;
use Tests\TestCase;

class IcsCalendarServiceTest extends TestCase
{
    public function test_it_formats_crew_details_from_raw_lines_when_structured_crew_metadata_is_missing(): void
    {
        $ics = app(IcsCalendarService::class)->serialize([
            [
                'title' => 'CKS 240 ICN-HKG',
                'type' => 'flight',
                'start' => '2026-06-15T23:45:00+00:00',
                'end' => '2026-06-16T03:45:00+00:00',
                'timezone' => 'UTC',
                'metadata' => [
                    'flight_number' => 'CKS 240',
                    'origin' => 'ICN',
                    'destination' => 'HKG',
                    'duty_raw_lines' => [
                        'Crew list',
                        'Name Crew Pos Base',
                        'Jane Doe 12345 FO CVG',
                        'John Smith 67890 DH AUS',
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('CREW LOGISTICS', $ics);
        $this->assertStringContainsString('Crew count: 2', $ics);
        $this->assertStringContainsString('Operating crew count: 1', $ics);
        $this->assertStringContainsString('Deadheading crew count: 1', $ics);
        $this->assertStringContainsString('Jane Doe (FO', $ics);
        $this->assertStringContainsString('John Smith (DH', $ics);
    }
}
