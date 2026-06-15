<?php

namespace Tests\Unit;

use App\Services\RosterParser;
use Tests\TestCase;

class RosterParserServiceTest extends TestCase
{
    public function test_it_parses_a_duty_block_into_a_flight_event(): void
    {
        $text = <<<'TEXT'
June 2026
Details
Duty start 22:44
Fri DH G4368 AUS-CVG 17:44 21:17 22:44 01:17 -
12Jun Duty end 01:17
TEXT;

        $parsed = app(RosterParser::class)->parse($text);
        $event = $parsed['calendar_events'][0];

        $this->assertSame('layover', $event['type']);
        $this->assertSame('AUS - CVG (G4368)', $event['title']);
        $this->assertSame('G4368', $event['metadata']['flight_number']);
        $this->assertSame('AUS', $event['metadata']['origin']);
        $this->assertSame('CVG', $event['metadata']['destination']);
        $this->assertTrue($event['metadata']['deadhead']);
        $this->assertSame('2026-06-12T17:44:00+00:00', $event['start']);
        $this->assertSame('2026-06-12T21:17:00+00:00', $event['end']);
    }

    public function test_it_parses_a_non_deadhead_duty_block_into_a_flight_event(): void
    {
        $text = <<<'TEXT'
June 2026
Details
Duty start 09:35
Sat 206 CVG-NRT 09:35 23:25 09:35 23:25 13:50 77X
13Jun Duty end 23:25
TEXT;

        $parsed = app(RosterParser::class)->parse($text);
        $event = $parsed['calendar_events'][0];

        $this->assertSame('flight', $event['type']);
        $this->assertSame('CVG - NRT (206)', $event['title']);
        $this->assertSame('206', $event['metadata']['flight_number']);
        $this->assertSame('CVG', $event['metadata']['origin']);
        $this->assertSame('NRT', $event['metadata']['destination']);
        $this->assertFalse($event['metadata']['deadhead']);
        $this->assertSame('2026-06-13T09:35:00+00:00', $event['start']);
        $this->assertSame('2026-06-13T23:25:00+00:00', $event['end']);
    }

    public function test_it_extracts_trip_summary_fields_from_roster_text(): void
    {
        $text = <<<'TEXT'
Trip Information
Date: 13Jun2026
Trip Id: 13131
Crew: 2FO
Homebase: AUS
Block Time: 54:15
TEXT;

        $parsed = app(RosterParser::class)->parse($text);
        $trip = $parsed['trip'];

        $this->assertSame('13131', $trip['trip_number']);
        $this->assertSame('FO', $trip['position']);
        $this->assertSame('AUS', $trip['base']);
        $this->assertSame('54:15', $trip['block_time']);
        $this->assertSame('13Jun2026', $trip['roster_range']);
        $this->assertSame([], $parsed['calendar_events']);
    }
}
