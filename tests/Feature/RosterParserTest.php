<?php

namespace Tests\Feature;

use App\Models\User;
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

        $response
            ->assertRedirect()
            ->assertSessionHas('result', function (array $result): bool {
                $parsed = $result['parsed'];

                return $result['type'] === 'roster'
                    && is_string($result['parse_key'] ?? null)
                    && $parsed['trip']['trip_number'] === '13131'
                    && $parsed['trip']['position'] === 'FO'
                    && count($parsed['calendar_events']) === 4
                    && is_string($parsed['calendar_events'][0]['download_id'] ?? null)
                    && $parsed['calendar_events'][0]['type'] === 'flight'
                    && $parsed['calendar_events'][0]['metadata']['flight_number'] === 'G4 368'
                    && $parsed['calendar_events'][0]['metadata']['origin'] === 'AUS'
                    && $parsed['calendar_events'][0]['metadata']['destination'] === 'CVG'
                    && $parsed['calendar_events'][1]['type'] === 'layover'
                    && $parsed['calendar_events'][1]['metadata']['hotel'] === 'Holiday Inn Express & Suites Florence - Cincinnati Airport - Vandercar Way'
                    && $parsed['calendar_events'][2]['type'] === 'duty'
                    && $parsed['calendar_events'][3]['metadata']['aircraft'] === '77X'
                    && $parsed['calendar_events'][3]['metadata']['block_time'] === '13:50h'
                    && $parsed['calendar_events'][3]['start'] === '2026-06-13T09:35:00+00:00';
            });
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

        $response
            ->assertRedirect()
                ->assertSessionHas('result', function (array $result): bool {
                return $result['type'] === 'hotel'
                    && is_string($result['parse_key'] ?? null)
                    && count($result['parsed']) === 1
                    && $result['parsed'][0]['type'] === 'layover';
                });
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

        $response
            ->assertRedirect()
            ->assertSessionHas('result', function (array $result): bool {
                return $result['filters'] === ['flight']
                    && is_string($result['parse_key'] ?? null)
                    && count($result['parsed']['calendar_events']) === 1
                    && is_string($result['parsed']['calendar_events'][0]['download_id'] ?? null)
                    && $result['parsed']['calendar_events'][0]['type'] === 'flight';
            });
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

        $response
            ->assertRedirect()
            ->assertSessionHas('result', function (array $result): bool {
                $events = $result['parsed']['calendar_events'];

                return count($events) === 6
                    && is_string($result['parse_key'] ?? null)
                    && $events[0]['type'] === 'flight'
                    && is_string($events[0]['download_id'] ?? null)
                    && $events[0]['metadata']['flight_number'] === 'G4 368'
                    && $events[0]['metadata']['origin'] === 'AUS'
                    && $events[3]['type'] === 'flight'
                    && $events[3]['metadata']['origin'] === 'CVG'
                    && $events[3]['metadata']['destination'] === 'NRT'
                    && $events[5]['type'] === 'layover'
                    && $events[5]['metadata']['hotel'] === 'Hyatt Regency Tokyo Bay';
            });
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
        $result = session('parsed_result');

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
        $result = session('parsed_result');
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

        $result = session('parsed_result');
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
        $firstResult = session('parsed_result');
        $firstEventId = $firstResult['parsed']['calendar_events'][0]['download_id'];

        $this->post(route('parse.roster'), ['text' => $secondText]);
        $secondResult = session('parsed_result');

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
}
