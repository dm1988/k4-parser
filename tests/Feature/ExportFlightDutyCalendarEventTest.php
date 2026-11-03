<?php

namespace Tests\Feature;

use App\Actions\Schedule\BuildScheduleResult;
use App\DTOs\DutyEvent;
use App\Models\User;
use App\Services\Infrastructure\EngineResultCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExportFlightDutyCalendarEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_flight_dto_survives_result_assembly_cache_and_per_event_export(): void
    {
        $result = app(BuildScheduleResult::class)->handle(
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
        Config::set('features.schedule_extractor.duty_export_for_all_users', true);

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

    /**
     * @param  array{string, string, string, string}  $localTimes
     */
    #[DataProvider('dutyDates')]
    public function test_it_exports_duty_dates_across_year_boundaries(
        string $flightStart,
        string $flightEnd,
        array $localTimes,
        string $dutyStart,
        string $dutyEnd,
    ): void {
        $this->cacheFlightForDutyExport($flightStart, $flightEnd, $localTimes);

        $response = $this->actingAs(User::factory()->make(['role' => 'admin']))
            ->get(route('parse.export.event.duty', ['eventId' => 'dutyDates', 'parse_key' => 'duty-dates']));

        $response->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8')
            ->assertSee('DTSTART:'.$dutyStart)
            ->assertSee('DTEND:'.$dutyEnd);
    }

    /**
     * @return array<string, array{string, string, array{string, string, string, string}, string, string}>
     */
    public static function dutyDates(): array
    {
        return [
            'local date in previous year' => [
                '2026-01-01T01:00:00+00:00', '2026-01-01T05:00:00+00:00',
                ['Dec 31 20:00', 'Jan 1 00:00', 'Dec 31 18:00', 'Jan 1 00:30'],
                '20251231T230000Z', '20260101T053000Z',
            ],
            'local date in next year' => [
                '2026-12-31T18:00:00+00:00', '2026-12-31T22:00:00+00:00',
                ['Jan 1 03:00', 'Jan 1 07:00', 'Jan 1 01:00', 'Jan 1 07:30'],
                '20261231T160000Z', '20261231T223000Z',
            ],
            'duty report in previous year' => [
                '2026-01-01T05:30:00+00:00', '2026-01-01T09:30:00+00:00',
                ['Jan 1 00:30', 'Jan 1 04:30', 'Dec 31 23:30', 'Jan 1 05:00'],
                '20260101T043000Z', '20260101T100000Z',
            ],
            'previous-day OCR correction at new year' => [
                '2025-12-31T23:45:00+00:00', '2026-01-01T03:45:00+00:00',
                ['Jan 1 08:45', 'Jan 1 11:45', 'Jan 1 06:45', 'Dec 31 12:00'],
                '20251231T214500Z', '20260101T040000Z',
            ],
            'ordinary midnight crossing' => [
                '2026-06-15T23:00:00+00:00', '2026-06-16T03:00:00+00:00',
                ['Jun 15 23:00', 'Jun 16 03:00', 'Jun 15 21:00', 'Jun 16 03:30'],
                '20260615T210000Z', '20260616T033000Z',
            ],
            'duty span of exactly thirty hours' => [
                '2026-06-16T04:00:00+00:00', '2026-06-16T08:00:00+00:00',
                ['Jun 16 04:00', 'Jun 16 08:00', 'Jun 15 04:00', 'Jun 16 10:00'],
                '20260615T040000Z', '20260616T100000Z',
            ],
        ];
    }

    /**
     * @param  array{string, string, string, string}  $localTimes
     */
    #[DataProvider('unavailableDutyDates')]
    public function test_it_returns_404_when_duty_dates_are_unavailable(array $localTimes): void
    {
        $this->cacheFlightForDutyExport('2026-06-16T08:00:00+00:00', '2026-06-16T12:00:00+00:00', $localTimes);

        $response = $this->actingAs(User::factory()->make(['role' => 'admin']))
            ->get(route('parse.export.event.duty', ['eventId' => 'dutyDates', 'parse_key' => 'duty-dates']));

        $response->assertNotFound();
    }

    /**
     * @return array<string, array{array{string, string, string, string}}>
     */
    public static function unavailableDutyDates(): array
    {
        return [
            'malformed local text' => [['Jun 16 08:00', 'Jun 16 12:00', 'not a date', 'Jun 16 12:30']],
            'impossible local date' => [['Jun 16 08:00', 'Jun 31 12:00', 'Jun 16 06:00', 'Jun 16 12:30']],
            'duty longer than thirty hours' => [['Jun 16 08:00', 'Jun 16 12:00', 'Jun 15 06:29', 'Jun 16 12:30']],
            'OCR correction needs two days' => [['Jun 16 08:00', 'Jun 16 12:00', 'Jun 16 06:00', 'Jun 14 12:30']],
        ];
    }

    /**
     * @param  array{string, string, string, string}  $localTimes
     */
    private function cacheFlightForDutyExport(string $flightStart, string $flightEnd, array $localTimes): void
    {
        session([
            'latest_parse_key' => 'duty-dates',
            'parsed_results_namespace' => 'duty-dates-session',
        ]);

        Cache::put($this->cacheKeyForSession('duty-dates'), [
            'type' => 'roster',
            'source' => 'text',
            'filters' => [],
            'parse_key' => 'duty-dates',
            'parsed' => [
                'calendar_events' => [[
                    'title' => 'CKS 240 ICN-HKG',
                    'type' => 'flight',
                    'start' => $flightStart,
                    'end' => $flightEnd,
                    'download_id' => 'dutyDates',
                    'legLocalStart' => $localTimes[0],
                    'legLocalEnd' => $localTimes[1],
                    'dutyLocalStart' => $localTimes[2],
                    'dutyLocalEnd' => $localTimes[3],
                ]],
            ],
        ]);
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
