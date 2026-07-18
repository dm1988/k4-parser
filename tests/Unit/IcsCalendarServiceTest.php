<?php

namespace Tests\Unit;

use App\DTOs\DutyEvent;
use App\DTOs\Flight;
use App\Services\IcsCalendarService;
use Tests\TestCase;

class IcsCalendarServiceTest extends TestCase
{
    public function test_it_preserves_calendar_and_event_export_contracts(): void
    {
        $this->travelTo('2026-07-18 15:30:00 UTC');

        $start = '2026-06-15T19:45:00-04:00';
        $end = '2026-06-16T00:45:00-03:00';
        $title = 'CKS 240, ICN; HKG\\Cargo';
        $flightAwareUrl = 'https://flightaware.com/live/flight/CKS240';

        $ics = app(IcsCalendarService::class)->serialize([
            new \stdClass,
            [
                'title' => $title,
                'type' => 'flight',
                'start' => $start,
                'end' => $end,
                'metadata' => [
                    'origin' => 'ICN',
                    'destination' => 'HKG',
                    'flightaware_url' => $flightAwareUrl,
                    'notes' => "Gate A, then B; bring paperwork\\passport\nSecond line",
                ],
            ],
            [
                'title' => 'Hotel Check-In',
                'type' => 'duty',
                'start' => '2026-06-16T05:00:00+00:00',
                'end' => '2026-06-16T06:00:00+00:00',
                'metadata' => [],
            ],
        ], ['trip_number' => '13131']);

        $unfoldedIcs = $this->unfold($ics);

        $this->assertSame(2, substr_count($unfoldedIcs, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('PRODID:-//Crew Compass//Roster Parser//EN', $unfoldedIcs);
        $this->assertStringContainsString('CALSCALE:GREGORIAN', $unfoldedIcs);
        $this->assertStringContainsString('METHOD:PUBLISH', $unfoldedIcs);
        $this->assertStringContainsString('X-WR-CALNAME:JCA Parsed Trip 13131', $unfoldedIcs);
        $this->assertStringContainsString('X-WR-CALDESC:Calendar export from Crew Compass JCA parser', $unfoldedIcs);
        $this->assertStringContainsString('UID:'.sha1($title.$start.$end).'@crew-compass', $unfoldedIcs);
        $this->assertStringContainsString('DTSTAMP:20260718T153000Z', $unfoldedIcs);
        $this->assertStringContainsString('DTSTART:20260615T234500Z', $unfoldedIcs);
        $this->assertStringContainsString('DTEND:20260616T034500Z', $unfoldedIcs);
        $this->assertStringContainsString('SUMMARY:CKS 240\\, ICN\\; HKG\\\\Cargo', $unfoldedIcs);
        $this->assertStringContainsString('URL:'.$flightAwareUrl, $unfoldedIcs);
        $this->assertStringContainsString('Gate A\\, then B\\; bring paperwork\\\\passport\\nSecond line', $unfoldedIcs);
        $this->assertStringNotContainsString('BEGIN:VTIMEZONE', $unfoldedIcs);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
    }

    public function test_it_serializes_non_flight_event_dtos(): void
    {
        $ics = app(IcsCalendarService::class)->serialize([
            DutyEvent::fromArray([
                'title' => 'Hotel Check-In',
                'type' => 'duty',
                'start' => '2026-06-16T05:00:00+00:00',
                'end' => '2026-06-16T06:00:00+00:00',
                'metadata' => ['station' => 'NRT'],
            ]),
        ]);

        $unfoldedIcs = $this->unfold($ics);

        $this->assertStringContainsString('SUMMARY:Hotel Check-In', $unfoldedIcs);
        $this->assertStringContainsString('DTSTART:20260616T050000Z', $unfoldedIcs);
        $this->assertStringContainsString('DTEND:20260616T060000Z', $unfoldedIcs);
    }

    public function test_it_serializes_flight_dtos(): void
    {
        $ics = app(IcsCalendarService::class)->serialize([
            Flight::fromArray([
                'title' => 'CKS 206 CVG-NRT',
                'type' => 'flight',
                'start' => '2026-06-13T09:35:00+00:00',
                'end' => '2026-06-13T23:25:00+00:00',
                'flightNumber' => 'CKS 206',
                'origin' => 'CVG',
                'destination' => 'NRT',
            ]),
        ]);

        $unfoldedIcs = $this->unfold($ics);

        $this->assertStringContainsString('SUMMARY:CKS 206 CVG-NRT', $unfoldedIcs);
        $this->assertStringContainsString('DTSTART:20260613T093500Z', $unfoldedIcs);
        $this->assertStringContainsString('DTEND:20260613T232500Z', $unfoldedIcs);
        $this->assertStringContainsString('Flight number: CKS 206', $unfoldedIcs);
    }

    public function test_it_folds_long_unicode_content_lines_without_corrupting_utf8(): void
    {
        $airportName = str_repeat('São Paulo–Guarulhos ✈ ', 12);

        $ics = app(IcsCalendarService::class)->serialize([[
            'title' => 'CKS 240 ICN-HKG',
            'type' => 'flight',
            'start' => '2026-06-15T23:45:00+00:00',
            'end' => '2026-06-16T03:45:00+00:00',
            'metadata' => ['airport_name' => $airportName],
        ]]);

        $this->assertTrue(mb_check_encoding($ics, 'UTF-8'));

        foreach (explode("\r\n", trim($ics)) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }

        $this->assertStringContainsString($airportName, $this->unfold($ics));
        $this->assertMatchesRegularExpression('/\r\n /', $ics);
    }

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

        $unfoldedIcs = $this->unfold($ics);

        $this->assertStringContainsString('CREW LOGISTICS', $unfoldedIcs);
        $this->assertStringContainsString('Crew count: 2', $unfoldedIcs);
        $this->assertStringContainsString('Operating crew count: 1', $unfoldedIcs);
        $this->assertStringContainsString('Deadheading crew count: 1', $unfoldedIcs);
        $this->assertStringContainsString('Jane Doe (FO', $unfoldedIcs);
        $this->assertStringContainsString('John Smith (DH', $unfoldedIcs);
    }

    private function unfold(string $ics): string
    {
        return preg_replace('/\r\n[ \t]/', '', $ics) ?? $ics;
    }
}
