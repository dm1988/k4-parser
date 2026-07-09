<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ExportFlightDutyCalendarEventTest extends TestCase
{
    public function test_it_exports_a_flight_duty_calendar_event(): void
    {
        $parseKey = '01JTESTPARSEKEYABC123';
        $eventId = '01JTESTEVENTKEYABC123';

        session([
            'latest_parse_key' => $parseKey,
            'parsed_results_namespace' => '01JTESTSESSIONKEYABC123',
        ]);

        Cache::put($this->cacheKeyForSession($parseKey), [
            'type' => 'roster',
            'source' => 'text',
            'filters' => [],
            'parse_key' => $parseKey,
            'parsed' => [
                'trip' => ['trip_number' => '13131'],
                'calendar_events' => [[
                    'title' => 'CKS 271 ICN-ANC',
                    'type' => 'flight',
                    'start' => '2026-06-26T23:45:00+00:00',
                    'end' => '2026-06-27T08:00:00+00:00',
                    'download_id' => $eventId,
                    'flightNumber' => 'CKS 271',
                    'origin' => 'ICN',
                    'destination' => 'ANC',
                    'legLocalStart' => 'Jun 26 19:45',
                    'legLocalEnd' => 'Jun 27 05:00',
                    'dutyLocalStart' => 'Jun 26 17:45',
                    'dutyLocalEnd' => 'Jun 27 10:40',
                    'metadata' => [],
                ]],
            ],
        ]);

        $response = $this
            ->actingAs(User::factory()->make())
            ->get(route('parse.export.event.duty', ['eventId' => $eventId, 'parse_key' => $parseKey]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8')
            ->assertHeader('content-disposition', 'attachment; filename="crew-compass-13131-duty-'.$eventId.'.ics"')
            ->assertSee('BEGIN:VCALENDAR')
            ->assertSee('BEGIN:VEVENT')
            ->assertSee('SUMMARY:Duty')
            ->assertSee('DTSTART:20260626T214500Z')
            ->assertSee('DTEND:20260627T134000Z')
            ->assertSee('Duty UTC start: Jun 26 21:45 Z')
            ->assertSee('Duty UTC end: Jun 27 13:40 Z')
            ->assertSee('Duty local start: Jun 26 17:45')
            ->assertSee('Duty local end: Jun 27 10:40')
            ->assertSee('Duration: 15h 55m');
    }

    public function test_it_exports_a_flight_duty_calendar_event_from_parse_key_cache(): void
    {
        $parseKey = '01JTESTPARSEKEYABC123';
        $eventId = '01JTESTEVENTKEYABC123';

        Cache::put("parsed_results:{$parseKey}", [
            'type' => 'roster',
            'source' => 'text',
            'filters' => [],
            'parse_key' => $parseKey,
            'parsed' => [
                'trip' => ['trip_number' => '13131'],
                'calendar_events' => [[
                    'title' => 'CKS 271 ICN-ANC',
                    'type' => 'flight',
                    'start' => '2026-06-26T23:45:00+00:00',
                    'end' => '2026-06-27T08:00:00+00:00',
                    'download_id' => $eventId,
                    'flightNumber' => 'CKS 271',
                    'origin' => 'ICN',
                    'destination' => 'ANC',
                    'legLocalStart' => 'Jun 26 19:45',
                    'legLocalEnd' => 'Jun 27 05:00',
                    'dutyLocalStart' => 'Jun 26 17:45',
                    'dutyLocalEnd' => 'Jun 27 10:40',
                    'metadata' => [],
                ]],
            ],
        ]);

        $response = $this
            ->actingAs(User::factory()->make())
            ->get(route('parse.export.event.duty', ['eventId' => $eventId, 'parse_key' => $parseKey]));

        $response
            ->assertOk()
            ->assertSee('SUMMARY:Duty')
            ->assertSee('DTSTART:20260626T214500Z')
            ->assertSee('DTEND:20260627T134000Z');
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
