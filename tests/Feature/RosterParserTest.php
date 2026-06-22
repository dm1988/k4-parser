<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RosterParserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->make());
    }

    public function test_roster_text_is_parsed_into_calendar_events(): void
    {
        $text = <<<'TEXT'
June 2026
Roster
Jun 12 22:44 - Jun 28 18:30 (17d)
Trip
13131
Pos Stn Layovers
FO
AUS CVG NRT HKG DWC HKG ANC
Block 54:15h
Details
Jun 12 22:44 - Jun 13 01:17
G4 368
Pos
AUS - CVG
DH
Jun 13 01:17 - Jun 13 07:35
CVG - Holiday Inn Express & Suites Florence - Cincinnati Airport - Vandercar Way
6:18h
Jun 13 07:35 - Jun 13 09:35
CVG
2:00
Jun 13 09:35 - Jun 13 23:25
K4 206
Pos
CVG - NRT
FO
AC
Block
77X
13:50h
TEXT;

        $response = $this->post(route('parse.roster'), ['text' => $text]);

        $response->assertRedirect();
        $response->assertSessionMissing('result');

        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);

        $result = Cache::get($this->cacheKeyForSession($parseKey));
        $this->assertIsArray($result);

        $parsed = $result['parsed'];

        $this->assertSame('13131', $parsed['trip']['trip_number']);
        $this->assertSame('FO', $parsed['trip']['position']);
        $this->assertCount(4, $parsed['calendar_events']);
        $this->assertIsString($parsed['calendar_events'][0]['download_id'] ?? null);
        $this->assertSame('flight', $parsed['calendar_events'][0]['type']);
        $this->assertSame('G4 368', $parsed['calendar_events'][0]['metadata']['flight_number']);
        $this->assertSame('AUS', $parsed['calendar_events'][0]['metadata']['origin']);
        $this->assertSame('CVG', $parsed['calendar_events'][0]['metadata']['destination']);
        $this->assertSame('layover', $parsed['calendar_events'][1]['type']);
        $this->assertSame('Holiday Inn Express & Suites Florence - Cincinnati Airport - Vandercar Way', $parsed['calendar_events'][1]['metadata']['hotel']);
        $this->assertSame('duty', $parsed['calendar_events'][2]['type']);
        $this->assertSame('77X', $parsed['calendar_events'][3]['metadata']['aircraft']);
        $this->assertSame('13:50h', $parsed['calendar_events'][3]['metadata']['block_time']);
        $this->assertSame('2026-06-13T09:35:00+00:00', $parsed['calendar_events'][3]['start']);
    }

    public function test_hotel_parser_returns_only_layover_events(): void
    {
        $text = <<<'TEXT'
June 2026
Details
Jun 13 01:17 - Jun 13 07:35
CVG - Holiday Inn Express & Suites Florence - Cincinnati Airport - Vandercar Way
6:18h
Jun 13 07:35 - Jun 13 09:35
CVG
2:00
TEXT;

        $response = $this->post(route('parse.hotel'), ['text' => $text]);

        $response->assertRedirect();
        $response->assertSessionMissing('result');

        $page = $this->get(route('parse.index'));
        $page->assertOk()->assertSee('Parsed Output');
    }

    public function test_roster_parser_can_filter_calendar_events(): void
    {
        $text = <<<'TEXT'
June 2026
Details
Jun 12 22:44 - Jun 13 01:17
G4 368
Pos
AUS - CVG
DH
Jun 13 01:17 - Jun 13 07:35
CVG - Holiday Inn Express & Suites Florence - Cincinnati Airport - Vandercar Way
6:18h
Jun 13 07:35 - Jun 13 09:35
CVG
2:00
TEXT;

        $response = $this->post(route('parse.roster'), [
            'text' => $text,
            'event_types' => ['flight'],
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('result');

        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);

        $result = Cache::get($this->cacheKeyForSession($parseKey));
        $this->assertIsArray($result);
        $this->assertCount(1, $result['parsed']['calendar_events']);
        $this->assertIsString($result['parsed']['calendar_events'][0]['download_id'] ?? null);
        $this->assertSame('flight', $result['parsed']['calendar_events'][0]['type']);
    }

    public function test_roster_parser_handles_noisy_image_ocr_output(): void
    {
        $text = <<<'TEXT'
June 2026 @) Jun 12 22:44 - Jun 28 18:30 (17d)
Trip Pos Stn Layovers n
13131 | FO. AUS CVG NRT HKG DWC HKG ANC
SS Block 54:15h _—_leg/duty/trip End time is exclusive
8 9 @ 1 120° 1384 Details
15 16 7 18 19 20 1 Jun 12 22:44 - Jun 13 01:17
= SS G4 368 Pos Vv
ee ee ee ee AUS - CVG | DH
Jun 13 01:17 - Jun 13 07:35
@ ry CVG - Holiday Inn Express & Suites Florence - Cincinnati Airport - Vandercar Way v
6:18h
Jun 13 07:35 - Jun 13 09:35
‘o) CVG
2:00
Jun 13 09:35 - Jun 13 23:25
@ K4 206 Pos AC Block Vv
CVG-NRT | FO 77X 13:50h
Jun 13 23:25 - Jun 13 23:55
‘o) NRT
0:30
Jun 13 23:55 - Jun 16 20:30
ry NRT - Hyatt Regency Tokyo Bay Vv
68:35h
TEXT;

        $response = $this->post(route('parse.roster'), ['text' => $text]);

        $response->assertRedirect();
        $response->assertSessionMissing('result');

        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);

        $result = Cache::get($this->cacheKeyForSession($parseKey));
        $this->assertIsArray($result);
        $events = $result['parsed']['calendar_events'];

        $this->assertCount(6, $events);
        $this->assertSame('flight', $events[0]['type']);
        $this->assertIsString($events[0]['download_id'] ?? null);
        $this->assertSame('G4 368', $events[0]['metadata']['flight_number']);
        $this->assertSame('AUS', $events[0]['metadata']['origin']);
        $this->assertSame('flight', $events[3]['type']);
        $this->assertSame('CVG', $events[3]['metadata']['origin']);
        $this->assertSame('NRT', $events[3]['metadata']['destination']);
        $this->assertSame('layover', $events[5]['type']);
        $this->assertSame('Hyatt Regency Tokyo Bay', $events[5]['metadata']['hotel']);
    }

    public function test_roster_parser_attaches_duty_lt_flight_info_to_matching_flight(): void
    {
        $text = <<<'TEXT'
May 2026
Details
May 16 07:00 - May 16 21:00
{ETD #5%50))
Work 2 8 @ K4 200 Pos AC Block A
a
EN CVG-NRT | FO 77X 14:00h
4 5 6 7 8 9 10
Tail id N793CK Leg LT
May 16 03:00 - May 17 06:00
Duty LT May 16 01:00 - May 17 06:30
EEE EEE Customer DHL 777 NET Catering Ordered
oT 120 138014 BY 17
Work
EEE EEE .
DEW ICN CVG NRT Flight Info
18 19 20 21 22 23 24 Scheduled Time:
TEXT;

        $response = $this->post(route('parse.roster'), ['text' => $text]);

        $response->assertRedirect();
        $response->assertSessionMissing('result');

        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);

        $result = Cache::get($this->cacheKeyForSession($parseKey));
        $this->assertIsArray($result);
        $events = $result['parsed']['calendar_events'];

        $this->assertCount(1, $events);
        $this->assertSame('flight', $events[0]['type']);
        $this->assertSame('CKS 200', $events[0]['metadata']['flight_number']);
        $this->assertSame('N793CK', $events[0]['metadata']['tail_number']);
        $this->assertSame('EEE', $events[0]['metadata']['duty_station']);
        $this->assertContains('EEE EEE Customer DHL 777 NET Catering Ordered', $events[0]['metadata']['raw_lines']);
        $this->assertContains('DEW ICN CVG NRT Flight Info', $events[0]['metadata']['duty_raw_lines']);
    }

    public function test_roster_parser_recovers_flight_event_from_noisy_ocr_image_text(): void
    {
        $text = <<<'TEXT'
Jun 15 23:45 - Jun 1603:45 — ETOyAG?5))
@ K4 240 Pos AC Block Cxn a
ICN - HKG | AFO 77X 4:00h -27:45h
Tail id N772CK Leg LT Jun 16 08:45 - Jun 16 11:45 Duty LT Jun 16 06:45 - Jun 15 12:00 Customer DHL 777 NET
Catering Ordered
Flight Info
Scheduled Time: Jun 15 13:00 - Jun 15 17:00
Crew list
Name Crew Pos Base
w Jesper Brandt Jensen 98765 (OP ete)
w Julio Rodriguez Batista 12456 FO EYW
aXe Cameron Stovold 36879 DH LAX
* David Gonzalez 34534 INZe) NUS
TEXT;

        $response = $this->post(route('parse.roster'), ['text' => $text]);

        $response->assertRedirect();
        $response->assertSessionMissing('result');

        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);

        $result = Cache::get($this->cacheKeyForSession($parseKey));
        $this->assertIsArray($result);
        $events = $result['parsed']['calendar_events'];

        $this->assertCount(1, $events);
        $this->assertSame('flight', $events[0]['type']);
        $this->assertSame('CKS 240', $events[0]['metadata']['flight_number']);
        $this->assertSame('ICN', $events[0]['metadata']['origin']);
        $this->assertSame('HKG', $events[0]['metadata']['destination']);
        $this->assertSame('N772CK', $events[0]['metadata']['tail_number']);
        $this->assertSame('77X', $events[0]['metadata']['aircraft']);
        $this->assertSame('4:00h', $events[0]['metadata']['block_time']);
        $this->assertSame(4, $events[0]['metadata']['crew_count']);
        $this->assertSame(3, $events[0]['metadata']['operating_crew_count']);
        $this->assertSame(1, $events[0]['metadata']['deadheading_crew_count']);
        $this->assertSame('2026-06-15T23:45:00+00:00', $events[0]['start']);
        $this->assertSame('2026-06-16T03:45:00+00:00', $events[0]['end']);
    }

    public function test_roster_parser_separates_operating_and_deadheading_crew_from_duty_lines(): void
    {
        $text = <<<'TEXT'
June 2026
Details
Jun 15 23:45 - Jun 16 03:45
K4 240
ICN - HKG | AFO 77X 4:00h
Tail id N772CK Leg LT
Jun 16 08:45 - Jun 16 11:45
Duty LT Jun 16 06:45 - Jun 15 12:00
Customer DHL 777 NET
Flight Info
Scheduled Time: Jun 15 13:00 - Jun 15 17:00
Crew list
Name Crew Pos Base
w Jesper Brandt Jensen 71022 (OP ete)
w Julio Rodriguez Batista 71559 FO EYW
aXe Cameron Stovold 71835 DH LAX
* David Gonzalez 72860 INZe) NUS
TEXT;

        $response = $this->post(route('parse.roster'), ['text' => $text]);

        $response->assertRedirect();
        $response->assertSessionMissing('result');

        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);

        $result = Cache::get($this->cacheKeyForSession($parseKey));
        $this->assertIsArray($result);
        $event = $result['parsed']['calendar_events'][0] ?? null;

        $this->assertIsArray($event);
        $this->assertSame('flight', $event['type']);
        $this->assertSame(4, $event['metadata']['crew_count']);
        $this->assertSame(3, $event['metadata']['operating_crew_count']);
        $this->assertSame(1, $event['metadata']['deadheading_crew_count']);
    }

    public function test_roster_parser_can_export_calendar_ics_from_parsed_result(): void
    {
        $text = <<<'TEXT'
        June 2026
        Details
        Jun 12 22:44 - Jun 13 01:17
        G4 368
        Pos
        AUS - CVG
        DH
        Jun 13 01:17 - Jun 13 07:35
        CVG - Holiday Inn Express & Suites Florence - Cincinnati Airport - Vandercar Way
        6:18h
        TEXT;

        $this->post(route('parse.roster'), ['text' => $text]);
        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);
        $result = Cache::get($this->cacheKeyForSession($parseKey));

        $response = $this->get(route('parse.export', ['parse_key' => $result['parse_key']]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8')
            ->assertHeader('content-disposition', 'attachment; filename="crew-compass.ics"')
            ->assertSee('BEGIN:VCALENDAR')
            ->assertSee('BEGIN:VEVENT')
            ->assertSee('SUMMARY:G4 368 AUS-CVG');
    }

    public function test_roster_parser_can_export_single_line_item_ics(): void
    {
        $text = <<<'TEXT'
        June 2026
        Details
        Jun 12 22:44 - Jun 13 01:17
        G4 368
        Pos
        AUS - CVG
        DH
        TEXT;

        $this->post(route('parse.roster'), ['text' => $text]);
        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);
        $result = Cache::get($this->cacheKeyForSession($parseKey));
        $eventId = $result['parsed']['calendar_events'][0]['download_id'];

        $response = $this->get(route('parse.export.event', ['eventId' => $eventId, 'parse_key' => $result['parse_key']]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8')
            ->assertHeader('content-disposition', 'attachment; filename="crew-compass-event-'.$eventId.'.ics"')
            ->assertSee('BEGIN:VCALENDAR')
            ->assertSee('BEGIN:VEVENT')
            ->assertSee('SUMMARY:G4 368 AUS-CVG')
            ->assertSee('DTSTART:20260612T224400Z')
            ->assertSee('DTEND:20260613T011700Z')
            ->assertSee('Type: Flight')
            ->assertSee('Flight number: G4 368')
            ->assertSee('Origin: AUS')
            ->assertSee('Destination: CVG')
            ->assertSee('Position: DH')
            ->assertDontSee('Aircraft:')
            ->assertDontSee('Block time:')
            ->assertDontSee('Raw lines:');
    }

    public function test_per_event_export_uses_stable_download_id_instead_of_filtered_index(): void
    {
        $text = <<<'TEXT'
        June 2026
        Details
        Jun 12 22:44 - Jun 13 01:17
        G4 368
        Pos
        AUS - CVG
        DH
        Jun 13 01:17 - Jun 13 07:35
        CVG - Holiday Inn Express & Suites Florence - Cincinnati Airport - Vandercar Way
        6:18h
        TEXT;

        $this->post(route('parse.roster'), [
            'text' => $text,
            'event_types' => ['layover'],
        ]);

        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);
        $result = Cache::get($this->cacheKeyForSession($parseKey));
        $event = $result['parsed']['calendar_events'][0];

        $response = $this->get(route('parse.export.event', [
            'eventId' => $event['download_id'],
            'parse_key' => $result['parse_key'],
        ]));

        $response
            ->assertOk()
            ->assertSee('Hotel: Holiday Inn Express & Suites Florence - Cincinnati Airport - Vandercar Way', false)
            ->assertDontSee('SUMMARY:G4 368 AUS-CVG');
    }

    public function test_export_links_can_target_an_older_parse_result_in_the_same_session(): void
    {
        $firstText = <<<'TEXT'
        June 2026
        Details
        Jun 12 22:44 - Jun 13 01:17
        G4 368
        Pos
        AUS - CVG
        DH
        TEXT;

        $secondText = <<<'TEXT'
        June 2026
        Details
        Jun 13 09:35 - Jun 13 23:25
        K4 206
        Pos
        CVG - NRT
        FO
        AC
        Block
        77X
        13:50h
        TEXT;

        $this->post(route('parse.roster'), ['text' => $firstText]);
        $firstParseKey = session('latest_parse_key');
        $this->assertIsString($firstParseKey);
        $firstResult = Cache::get($this->cacheKeyForSession($firstParseKey));
        $firstEventId = $firstResult['parsed']['calendar_events'][0]['download_id'];

        $this->post(route('parse.roster'), ['text' => $secondText]);
        $secondParseKey = session('latest_parse_key');
        $this->assertIsString($secondParseKey);
        $secondResult = Cache::get($this->cacheKeyForSession($secondParseKey));

        $oldPageResponse = $this->get(route('parse.export.event', [
            'eventId' => $firstEventId,
            'parse_key' => $firstResult['parse_key'],
        ]));

        $currentPageResponse = $this->get(route('parse.export.event', [
            'eventId' => $secondResult['parsed']['calendar_events'][0]['download_id'],
            'parse_key' => $secondResult['parse_key'],
        ]));

        $oldPageResponse
            ->assertOk()
            ->assertSee('SUMMARY:G4 368 AUS-CVG')
            ->assertDontSee('SUMMARY:K4 206 CVG-NRT');

        $currentPageResponse
            ->assertOk()
            ->assertSee('SUMMARY:CKS 206 CVG-NRT')
            ->assertDontSee('SUMMARY:G4 368 AUS-CVG');
    }

    public function test_export_cannot_access_another_sessions_cached_parse_result(): void
    {
        $text = <<<'TEXT'
        June 2026
        Details
        Jun 12 22:44 - Jun 13 01:17
        G4 368
        Pos
        AUS - CVG
        DH
        TEXT;

        $this->post(route('parse.roster'), ['text' => $text]);

        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);
        $originalNamespace = $this->sessionCacheNamespace();

        $result = Cache::get($this->cacheKeyForSession($parseKey));
        $this->assertIsArray($result);

        session()->invalidate();
        session()->start();
        $this->assertNotSame($originalNamespace, $this->sessionCacheNamespace());

        $response = $this->get(route('parse.export', ['parse_key' => $parseKey]));

        $response->assertNotFound();
    }

    private function cacheKeyForSession(string $parseKey): string
    {
        return 'sessions:'.$this->sessionCacheNamespace().":parsed_results:{$parseKey}";
    }

    private function sessionCacheNamespace(): string
    {
        return (string) session('parsed_results_namespace', session()->getId());
    }
}
