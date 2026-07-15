<?php

namespace Tests\Unit;

use App\Models\Airline;
use App\Services\RosterParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterParserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_a_duty_block_into_a_flight_event(): void
    {
        Airline::query()->create([
            'name' => 'Allegiant Air',
            'iata_code' => 'G4',
            'active' => true,
        ]);

        $text = <<<'TEXT'
June 2026
Details
Duty start 22:44
Fri DH G4368 AUS-CVG 17:44 21:17 22:44 01:17 -
12Jun Duty end 01:17
TEXT;

        $parsed = app(RosterParser::class)->parse($text);
        $event = $parsed['calendar_events'][0];

        $this->assertSame('deadhead', $event['type']);
        $this->assertSame('AUS - CVG (G4 368)', $event['title']);
        $this->assertSame('G4 368', $event['metadata']['flight_number']);
        $this->assertSame('Allegiant Air', $event['metadata']['airline_name']);
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

    public function test_it_falls_back_to_the_bundled_airline_data_when_the_database_has_no_matching_airline(): void
    {
        $text = <<<'TEXT'
July 2026
Details
Duty start 15:09
Sun DH UA5445 LAX-AUS 15:09 18:29 15:09 18:29 -
5Jul Duty end 18:29
TEXT;

        $parsed = app(RosterParser::class)->parse($text);
        $event = $parsed['calendar_events'][0];

        $this->assertSame('LAX - AUS (UA 5445)', $event['title']);
        $this->assertSame('UA 5445', $event['metadata']['flight_number']);
        $this->assertSame('United Airlines', $event['metadata']['airline_name']);
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
