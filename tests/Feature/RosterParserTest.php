<?php

namespace Tests\Feature;

use Tests\TestCase;

class RosterParserTest extends TestCase
{
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
                    && $parsed['trip']['trip_number'] === '13131'
                    && $parsed['trip']['position'] === 'FO'
                    && count($parsed['calendar_events']) === 4
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
                    && count($result['parsed']) === 1
                    && $result['parsed'][0]['type'] === 'layover';
            });
    }
}
