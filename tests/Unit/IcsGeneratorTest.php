<?php

namespace Tests\Unit;

use App\DTOs\DutyEvent;
use App\DTOs\Flight;
use App\Models\User;
use App\Services\Calendar\IcsGenerator;
use Tests\TestCase;

class IcsGeneratorTest extends TestCase
{
    public function test_tracker_links_use_current_preferences_instead_of_cached_download_urls(): void
    {
        $user = User::factory()->make(['flight_tracker_preference' => 'flightradar24']);
        $this->actingAs($user);
        $flight = Flight::fromArray([
            'type' => 'flight', 'title' => 'CKS 240 ICN-HKG', 'start' => '2026-10-12T01:00:00Z', 'end' => '2026-10-12T05:00:00Z',
            'tailNumber' => 'N772CK', 'flightNumber' => 'CKS 240', 'origin' => 'ICN', 'destination' => 'HKG',
            'downloadUrl' => route('parse.export.event', ['eventId' => 'event-id', 'parse_key' => 'parse-id']),
            'metadata' => ['flightaware_url' => 'https://www.flightaware.com/live/flight/N772CK'],
        ]);
        $generator = app(IcsGenerator::class);

        $ics = $this->unfold($generator->serialize([$flight]));
        $this->assertStringContainsString('URL:https://www.flightradar24.com/data/aircraft/n772ck', $ics);
        $this->assertStringNotContainsString('URL:http://localhost/parse', $ics);

        $user->flight_tracker_preference = 'flightaware';
        $ics = $this->unfold($generator->serialize([$flight]));
        $this->assertStringContainsString('URL:https://www.flightaware.com/live/flight/N772CK', $ics);
        $this->assertStringNotContainsString('flightradar24', $ics);
    }

    public function test_deadheads_without_tails_use_flight_numbers_and_non_flight_events_have_no_tracker_url(): void
    {
        $this->actingAs(User::factory()->make(['flight_tracker_preference' => 'flightradar24']));
        $ics = $this->unfold(app(IcsGenerator::class)->serialize([
            ['type' => 'duty', 'title' => 'UA 123 LAX-AUS', 'start' => '2026-10-12T01:00:00Z', 'end' => '2026-10-12T03:00:00Z',
                'metadata' => ['duty_type' => 'deadhead', 'origin' => 'LAX', 'destination' => 'AUS', 'flight_number' => 'UA 123']],
            ['type' => 'duty', 'title' => 'R2 LAX', 'start' => '2026-10-13T01:00:00Z', 'end' => '2026-10-13T09:00:00Z',
                'metadata' => ['duty_type' => 'R2', 'flightaware_url' => 'https://example.test/stale']],
        ]));

        $this->assertStringContainsString('URL:https://www.flightradar24.com/data/flights/ua123', $ics);
        $this->assertSame(1, substr_count($ics, 'URL:'));
        $this->assertStringNotContainsString('https://example.test/stale', $ics);
    }

    public function test_it_preserves_calendar_and_event_export_contracts(): void
    {
        $this->travelTo('2026-07-18 15:30:00 UTC');

        $start = '2026-06-15T19:45:00-04:00';
        $end = '2026-06-16T00:45:00-03:00';
        $title = 'CKS 240, ICN; HKG\\Cargo';
        $flightAwareUrl = 'https://www.flightaware.com/live/flight/CKS240';

        $ics = app(IcsGenerator::class)->serialize([
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
                    'flight_number' => 'CKS 240',
                    'local_start' => '06-16 08:45',
                    'local_end' => '06-16 11:45',
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
        $this->assertStringContainsString('PRODID:-//Crew Compass//Roster Extractor//EN', $unfoldedIcs);
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
        $this->assertStringNotContainsString('Local start:', $unfoldedIcs);
        $this->assertStringNotContainsString('Local end:', $unfoldedIcs);
        $this->assertStringNotContainsString('BEGIN:VTIMEZONE', $unfoldedIcs);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
    }

    public function test_it_serializes_non_flight_event_dtos(): void
    {
        $ics = app(IcsGenerator::class)->serialize([
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
        $ics = app(IcsGenerator::class)->serialize([
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
        $this->assertStringContainsString('Flight: CKS 206', $unfoldedIcs);
        $this->assertStringContainsString('Route: CVG ➔ NRT', $unfoldedIcs);
        $this->assertStringContainsString('UTC:   Jun 13 09:35z ➔ 23:25z', $unfoldedIcs);
    }

    public function test_it_folds_long_unicode_content_lines_without_corrupting_utf8(): void
    {
        $airportName = str_repeat('São Paulo–Guarulhos ✈ ', 12);

        $ics = app(IcsGenerator::class)->serialize([[
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
        $ics = app(IcsGenerator::class)->serialize([
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

        $this->assertStringContainsString('👥 CREW (1 Ops / 1 DH)', $unfoldedIcs);
        $this->assertStringContainsString('• Jane Doe   │ FO │ CVG │ #12345', $unfoldedIcs);
        $this->assertStringContainsString('• John Smith │ DH │ AUS │ #67890', $unfoldedIcs);
    }

    private function unfold(string $ics): string
    {
        return preg_replace('/\r\n[ \t]/', '', $ics) ?? $ics;
    }
}
