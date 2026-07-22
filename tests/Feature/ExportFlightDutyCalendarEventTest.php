<?php

namespace Tests\Feature;

use App\Actions\BuildParserResult;
use App\DTOs\DutyEvent;
use App\Models\User;
use App\Services\Infrastructure\EngineResultCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ExportFlightDutyCalendarEventTest extends TestCase
{
    public function test_non_flight_dto_survives_result_assembly_cache_and_per_event_export(): void
    {
        $result = app(BuildParserResult::class)->handle(
            type: 'roster',
            source: 'text',
            documentType: null,
            parsed: [
                'trip' => ['trip_number' => '13131'],
                'calendar_events' => [
                    DutyEvent::fromArray([
                        'title' => 'Hotel Check-In',
                        'type' => 'duty',
                        'start' => '2026-06-13T14:00:00+00:00',
                        'end' => '2026-06-13T16:00:00+00:00',
                        'metadata' => ['station' => 'NRT'],
                    ]),
                ],
            ],
        );
        app(EngineResultCache::class)->put($result);

        /** @var DutyEvent $event */
        $event = $result->parsed['calendar_events'][0];
        $this->assertNotNull($event->downloadId);

        $response = $this
            ->actingAs(User::factory()->make())
            ->get(route('parse.export.event', [
                'eventId' => $event->downloadId,
                'parse_key' => $result->parseKey,
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8')
            ->assertSee('SUMMARY:Hotel Check-In')
            ->assertSee('DTSTART:20260613T140000Z')
            ->assertSee('DTEND:20260613T160000Z');
    }

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
                    'title' => 'CKS 240 ICN-HKG',
                    'type' => 'flight',
                    'start' => '2026-06-15T23:45:00+00:00',
                    'end' => '2026-06-16T03:45:00+00:00',
                    'download_id' => $eventId,
                    'flightNumber' => 'CKS 240',
                    'origin' => 'ICN',
                    'destination' => 'HKG',
                    'legLocalStart' => 'Jun 16 08:45',
                    'legLocalEnd' => 'Jun 16 11:45',
                    'dutyLocalStart' => 'Jun 16 06:45',
                    'dutyLocalEnd' => 'Jun 15 12:00',
                    'metadata' => [],
                ]],
            ],
        ]);

        $response = $this
            ->actingAs(User::factory()->make([
                'role' => 'admin',
            ]))
            ->get(route('parse.export.event.duty', ['eventId' => $eventId, 'parse_key' => $parseKey]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8')
            ->assertHeader('content-disposition', 'attachment; filename="crew-compass-13131-duty-'.$eventId.'.ics"');

        $ics = $this->unfoldIcs($response->getContent());

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('SUMMARY:Duty', $ics);
        $this->assertStringContainsString('DTSTART:20260615T214500Z', $ics);
        $this->assertStringContainsString('DTEND:20260616T040000Z', $ics);
        $this->assertStringContainsString('Duty UTC start: Jun 15 21:45 Z', $ics);
        $this->assertStringContainsString('Duty UTC end: Jun 16 04:00 Z', $ics);
        $this->assertStringContainsString('Duty local start: Jun 16 06:45', $ics);
        $this->assertStringContainsString('Duty local end: Jun 15 12:00', $ics);
        $this->assertStringContainsString('Duration: 6h 15m', $ics);
    }

    public function test_it_exports_a_flight_duty_calendar_event_from_parse_key_cache(): void
    {
        $parseKey = '01JTESTPARSEKEYABC123';
        $eventId = '01JTESTEVENTKEYABC123';
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        Cache::put("parsed_results:{$parseKey}", [
            'owner_id' => $user->id,
            'result' => [
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
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('parse.export.event.duty', ['eventId' => $eventId, 'parse_key' => $parseKey]));

        $response
            ->assertOk()
            ->assertSee('SUMMARY:Duty')
            ->assertSee('DTSTART:20260626T214500Z')
            ->assertSee('DTEND:20260627T134000Z');
    }

    public function test_non_admin_users_can_export_duty_when_the_feature_is_enabled_for_all_users(): void
    {
        Config::set('features.schedule_parser.duty_export_for_all_users', true);

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
                    'title' => 'CKS 240 ICN-HKG',
                    'type' => 'flight',
                    'start' => '2026-06-15T23:45:00+00:00',
                    'end' => '2026-06-16T03:45:00+00:00',
                    'download_id' => $eventId,
                    'flightNumber' => 'CKS 240',
                    'origin' => 'ICN',
                    'destination' => 'HKG',
                    'legLocalStart' => 'Jun 16 08:45',
                    'legLocalEnd' => 'Jun 16 11:45',
                    'dutyLocalStart' => 'Jun 16 06:45',
                    'dutyLocalEnd' => 'Jun 15 12:00',
                    'metadata' => [],
                ]],
            ],
        ]);

        $response = $this
            ->actingAs(User::factory()->make())
            ->get(route('parse.export.event.duty', ['eventId' => $eventId, 'parse_key' => $parseKey]));

        $response->assertOk();
    }

    private function cacheKeyForSession(string $parseKey): string
    {
        return 'sessions:'.$this->sessionCacheNamespace().":parsed_results:{$parseKey}";
    }

    private function sessionCacheNamespace(): string
    {
        return (string) session('parsed_results_namespace', session()->getId());
    }

    private function unfoldIcs(string $ics): string
    {
        return preg_replace('/\r\n[ \t]/', '', $ics) ?? $ics;
    }
}
