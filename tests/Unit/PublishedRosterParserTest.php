<?php

namespace Tests\Unit;

use App\Services\PublishedRosterParser;
use Tests\TestCase;

class PublishedRosterParserTest extends TestCase
{
    public function test_it_parses_published_roster_text_into_events(): void
    {
        $text = <<<'TEXT'
Published Roster
Planning period:June 2026Fleet:All fleetsRank:All ranks
72860, Gonzalez David
Rank: FO	Base:KEF	Seniority:745
Passports:US (12Sep2028)Medical:MED (01Oct2026)Line check:777_LC (01Dec2026)Qualifications:B777
DateReport (UTC)TagsPosActivityFromTo Start (UTC)End (UTC)A/CLayoverTrip ID
12 Fri	FO  OFF
DHG4368AUS 22:44	13131
13 Sat	DHG4368 CVG	01:17 06:18
07:35	FO  206CVGNRT10:38 23:3177X11:59
14 Sun	FO   R2NRT 12:00 22:00
15 Mon	DHOZ107NRTICN00:00 02:30 20:50
23:50
16 Tue	AFO  240ICNHKG02:54 07:0077X
DHCX711HKGSIN09:00 12:50 22:30
17 Wed11:50	FO  265SINNGO15:25 22:0077X32:30
19 Fri07:00	FO  252NGOHKG09:00 13:1577V151:15
25 Thu21:00	FO  243HKG 23:00	77X
26 Fri	FO  243 ANC	09:1077X48:05
28 Sun09:45	FO  201ANCCVG11:45 18:0077X
01Jun-30Jun2026	Jan - Jun
OFF Days	9	33
Block time	58:01	273:04
DH time	25:27
Block time + DH time 83:28
Created 17Jun2026 02:12 (UTC) by 72860	  1 (  1)
TEXT;

        $parsed = app(PublishedRosterParser::class)->parse($text);
        $events = $parsed['calendar_events'];
        $trip = $parsed['trip'];

        $this->assertSame('FO', $trip['position']);
        $this->assertSame('KEF', $trip['base']);
        $this->assertSame('58:01', $trip['block_time']);
        $this->assertSame('01Jun-30Jun2026', $trip['roster_range']);
        $this->assertContains('CVG', $trip['layovers']);
        $this->assertContains('NRT', $trip['layovers']);
        $this->assertContains('HKG', $trip['layovers']);

        $this->assertGreaterThanOrEqual(10, count($events));

        $this->assertSame('flight', $events[0]['type']);
        $this->assertSame('G4368', $events[0]['metadata']['flight_number']);
        $this->assertSame('AUS', $events[0]['metadata']['origin']);
        $this->assertSame('CVG', $events[0]['metadata']['destination']);
        $this->assertTrue($events[0]['metadata']['deadhead']);
        $this->assertSame('13131', $events[0]['metadata']['trip_id']);
        $this->assertSame('2026-06-12T22:44:00+00:00', $events[0]['start']);
        $this->assertSame('2026-06-13T01:17:00+00:00', $events[0]['end']);

        $this->assertSame('layover', $events[1]['type']);
        $this->assertSame('CVG', $events[1]['metadata']['station']);
        $this->assertSame('06:18', $events[1]['metadata']['layover_duration']);
        $this->assertSame('2026-06-13T07:35:00+00:00', $events[1]['end']);

        $cks206 = collect($events)->firstWhere('metadata.flight_number', 'CKS 206');
        $this->assertNotNull($cks206);
        $this->assertSame('CVG', $cks206['metadata']['origin']);
        $this->assertSame('NRT', $cks206['metadata']['destination']);
        $this->assertSame('77X', $cks206['metadata']['aircraft']);
        $this->assertSame('2026-06-13T10:38:00+00:00', $cks206['start']);
        $this->assertSame('2026-06-13T23:31:00+00:00', $cks206['end']);

        $hkgAnc = collect($events)->firstWhere('metadata.flight_number', 'CKS 243');
        $this->assertNotNull($hkgAnc);
        $this->assertSame('HKG', $hkgAnc['metadata']['origin']);
        $this->assertSame('ANC', $hkgAnc['metadata']['destination']);
        $this->assertSame('2026-06-25T23:00:00+00:00', $hkgAnc['start']);
        $this->assertSame('2026-06-26T09:10:00+00:00', $hkgAnc['end']);
    }
}
