<?php

namespace Tests\Unit;

use App\Services\Calendar\FlightDutyEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FlightDutyEventTest extends TestCase
{
    public function test_it_rejects_invalid_utc_times(): void
    {
        $this->assertNull(app(FlightDutyEvent::class)->buildFromFlight([
            'type' => 'flight',
            'start' => 'not a date',
            'end' => '2026-06-27T08:00:00+00:00',
        ]));
    }

    public function test_it_builds_a_duty_calendar_event_from_flight_and_local_duty_offsets(): void
    {
        $event = app(FlightDutyEvent::class)->buildFromFlight([
            'title' => 'CKS 271 ICN-ANC',
            'type' => 'flight',
            'start' => '2026-06-26T23:45:00+00:00',
            'end' => '2026-06-27T08:00:00+00:00',
            'timezone' => 'UTC',
            'metadata' => [
                'flight_number' => 'CKS 271',
                'origin' => 'ICN',
                'destination' => 'ANC',
                'leg_local_start' => 'Jun 26 19:45',
                'leg_local_end' => 'Jun 27 05:00',
                'duty_local_start' => 'Jun 26 17:45',
                'duty_local_end' => 'Jun 27 10:40',
            ],
        ]);

        $this->assertIsArray($event);
        $this->assertSame('Duty', $event['title']);
        $this->assertSame('duty', $event['type']);
        $this->assertSame('flight_duty', $event['metadata']['duty_type']);
        $this->assertSame('2026-06-26T21:45:00+00:00', $event['start']);
        $this->assertSame('2026-06-27T13:40:00+00:00', $event['end']);
        $this->assertSame('Jun 26 21:45 Z', $event['metadata']['duty_utc_start']);
        $this->assertSame('Jun 27 13:40 Z', $event['metadata']['duty_utc_end']);
        $this->assertSame('Jun 26 17:45', $event['metadata']['duty_local_start']);
        $this->assertSame('Jun 27 10:40', $event['metadata']['duty_local_end']);
        $this->assertSame('15h 55m', $event['metadata']['duration']);
    }

    public function test_it_returns_null_when_local_duty_times_are_missing(): void
    {
        $event = app(FlightDutyEvent::class)->buildFromFlight([
            'title' => 'CKS 271 ICN-ANC',
            'type' => 'flight',
            'start' => '2026-06-26T23:45:00+00:00',
            'end' => '2026-06-27T08:00:00+00:00',
            'metadata' => [
                'leg_local_start' => 'Jun 26 19:45',
                'leg_local_end' => 'Jun 27 05:00',
            ],
        ]);

        $this->assertNull($event);
    }

    public function test_it_builds_from_a_cached_flight_dto_array_with_top_level_local_times(): void
    {
        $event = app(FlightDutyEvent::class)->buildFromFlight([
            'title' => 'CKS 271 ICN-ANC',
            'type' => 'flight',
            'start' => '2026-06-26T23:45:00+00:00',
            'end' => '2026-06-27T08:00:00+00:00',
            'flightNumber' => 'CKS 271',
            'legLocalStart' => 'Jun 26 19:45',
            'legLocalEnd' => 'Jun 27 05:00',
            'dutyLocalStart' => 'Jun 26 17:45',
            'dutyLocalEnd' => 'Jun 27 10:40',
            'origin' => 'ICN',
            'destination' => 'ANC',
            'metadata' => [],
        ]);

        $this->assertIsArray($event);
        $this->assertSame('2026-06-26T21:45:00+00:00', $event['start']);
        $this->assertSame('2026-06-27T13:40:00+00:00', $event['end']);
        $this->assertSame('CKS 271', $event['metadata']['flight_number']);
        $this->assertSame('Jun 26 17:45', $event['metadata']['duty_local_start']);
        $this->assertSame('Jun 26 19:45', $event['metadata']['flight_local_start']);
    }

    public function test_it_rolls_an_ocr_duty_local_end_forward_when_it_precedes_the_flight_local_end(): void
    {
        $event = app(FlightDutyEvent::class)->buildFromFlight([
            'title' => 'CKS 240 ICN-HKG',
            'type' => 'flight',
            'start' => '2026-06-15T23:45:00+00:00',
            'end' => '2026-06-16T03:45:00+00:00',
            'metadata' => [
                'flight_number' => 'CKS 240',
                'origin' => 'ICN',
                'destination' => 'HKG',
                'leg_local_start' => 'Jun 16 08:45',
                'leg_local_end' => 'Jun 16 11:45',
                'duty_local_start' => 'Jun 16 06:45',
                'duty_local_end' => 'Jun 15 12:00',
            ],
        ]);

        $this->assertIsArray($event);
        $this->assertSame('2026-06-15T21:45:00+00:00', $event['start']);
        $this->assertSame('2026-06-16T04:00:00+00:00', $event['end']);
        $this->assertSame('6h 15m', $event['metadata']['duration']);
    }

    /**
     * @param  array{string, string, string, string}  $localTimes
     */
    #[DataProvider('localDateContexts')]
    public function test_it_resolves_local_dates_using_the_flight_context(
        string $flightStart,
        string $flightEnd,
        array $localTimes,
        string $dutyStart,
        string $dutyEnd,
    ): void {
        $event = app(FlightDutyEvent::class)->buildFromFlight([
            'type' => 'flight',
            'start' => $flightStart,
            'end' => $flightEnd,
            'legLocalStart' => $localTimes[0],
            'legLocalEnd' => $localTimes[1],
            'dutyLocalStart' => $localTimes[2],
            'dutyLocalEnd' => $localTimes[3],
        ]);

        $this->assertIsArray($event);
        $this->assertSame($dutyStart, $event['start']);
        $this->assertSame($dutyEnd, $event['end']);
    }

    /**
     * @return array<string, array{string, string, array{string, string, string, string}, string, string}>
     */
    public static function localDateContexts(): array
    {
        return [
            'local departure in previous year' => [
                '2026-01-01T01:00:00+00:00', '2026-01-01T05:00:00+00:00',
                ['Dec 31 20:00', 'Jan 1 00:00', 'Dec 31 18:00', 'Jan 1 00:30'],
                '2025-12-31T23:00:00+00:00', '2026-01-01T05:30:00+00:00',
            ],
            'local arrival in next year' => [
                '2026-12-31T18:00:00+00:00', '2026-12-31T22:00:00+00:00',
                ['Jan 1 03:00', 'Jan 1 07:00', 'Jan 1 01:00', 'Jan 1 07:30'],
                '2026-12-31T16:00:00+00:00', '2026-12-31T22:30:00+00:00',
            ],
            'UTC crosses into next year with local times ahead' => [
                '2026-12-31T23:00:00+00:00', '2027-01-01T03:00:00+00:00',
                ['Jan 1 08:00', 'Jan 1 12:00', 'Jan 1 06:00', 'Jan 1 12:30'],
                '2026-12-31T21:00:00+00:00', '2027-01-01T03:30:00+00:00',
            ],
            'UTC crosses into next year with local times behind' => [
                '2026-12-31T23:00:00+00:00', '2027-01-01T03:00:00+00:00',
                ['Dec 31 18:00', 'Dec 31 22:00', 'Dec 31 16:00', 'Dec 31 22:30'],
                '2026-12-31T21:00:00+00:00', '2027-01-01T03:30:00+00:00',
            ],
            'duty report belongs to previous year' => [
                '2026-01-01T05:30:00+00:00', '2026-01-01T09:30:00+00:00',
                ['Jan 1 00:30', 'Jan 1 04:30', 'Dec 31 23:30', 'Jan 1 05:00'],
                '2026-01-01T04:30:00+00:00', '2026-01-01T10:00:00+00:00',
            ],
            'duty release belongs to next year' => [
                '2026-12-31T18:00:00+00:00', '2026-12-31T22:45:00+00:00',
                ['Dec 31 19:00', 'Dec 31 23:45', 'Dec 31 17:00', 'Jan 1 00:00'],
                '2026-12-31T16:00:00+00:00', '2026-12-31T23:00:00+00:00',
            ],
            'ordinary midnight crossing' => [
                '2026-06-15T23:00:00+00:00', '2026-06-16T03:00:00+00:00',
                ['Jun 15 23:00', 'Jun 16 03:00', 'Jun 15 21:00', 'Jun 16 03:30'],
                '2026-06-15T21:00:00+00:00', '2026-06-16T03:30:00+00:00',
            ],
            'arrival clock precedes departure across time zones' => [
                '2026-01-01T00:00:00+00:00', '2026-01-01T10:00:00+00:00',
                ['Jan 1 09:00', 'Jan 1 00:00', 'Jan 1 07:00', 'Jan 1 00:30'],
                '2025-12-31T22:00:00+00:00', '2026-01-01T10:30:00+00:00',
            ],
            'previous-day OCR correction crosses year boundary' => [
                '2025-12-31T23:45:00+00:00', '2026-01-01T03:45:00+00:00',
                ['Jan 1 08:45', 'Jan 1 11:45', 'Jan 1 06:45', 'Dec 31 12:00'],
                '2025-12-31T21:45:00+00:00', '2026-01-01T04:00:00+00:00',
            ],
            'leap day in flight year' => [
                '2024-02-29T08:00:00+00:00', '2024-02-29T12:00:00+00:00',
                ['Feb 29 08:00', 'Feb 29 12:00', 'Feb 29 06:00', 'Feb 29 12:30'],
                '2024-02-29T06:00:00+00:00', '2024-02-29T12:30:00+00:00',
            ],
            'zero-padded source days' => [
                '2026-06-01T08:00:00+00:00', '2026-06-01T12:00:00+00:00',
                ['Jun 01 08:00', 'Jun 01 12:00', 'Jun 01 06:00', 'Jun 01 12:30'],
                '2026-06-01T06:00:00+00:00', '2026-06-01T12:30:00+00:00',
            ],
            'duty span of exactly thirty hours' => [
                '2026-06-16T04:00:00+00:00', '2026-06-16T08:00:00+00:00',
                ['Jun 16 04:00', 'Jun 16 08:00', 'Jun 15 04:00', 'Jun 16 10:00'],
                '2026-06-15T04:00:00+00:00', '2026-06-16T10:00:00+00:00',
            ],
            'local offset of fourteen hours' => [
                '2026-06-15T23:00:00+00:00', '2026-06-16T03:00:00+00:00',
                ['Jun 16 13:00', 'Jun 16 17:00', 'Jun 16 11:00', 'Jun 16 17:30'],
                '2026-06-15T21:00:00+00:00', '2026-06-16T03:30:00+00:00',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     */
    #[DataProvider('invalidLocalTimes')]
    public function test_it_returns_null_for_invalid_or_implausible_local_times(array $overrides): void
    {
        $event = app(FlightDutyEvent::class)->buildFromFlight(array_replace([
            'type' => 'flight',
            'start' => '2026-06-16T08:00:00+00:00',
            'end' => '2026-06-16T12:00:00+00:00',
            'legLocalStart' => 'Jun 16 08:00',
            'legLocalEnd' => 'Jun 16 12:00',
            'dutyLocalStart' => 'Jun 16 06:00',
            'dutyLocalEnd' => 'Jun 16 12:30',
        ], $overrides));

        $this->assertNull($event);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function invalidLocalTimes(): array
    {
        $cases = [];

        foreach (['legLocalStart', 'legLocalEnd', 'dutyLocalStart', 'dutyLocalEnd'] as $field) {
            foreach (['not a date', 'Jun 31 08:00', 'Jun 16 24:00', 'Jun 16 08:60', "Jun 16 08:00\0", 'Jun 16 08:00 extra'] as $value) {
                $cases[$field.' '.json_encode($value)] = [[$field => $value]];
            }
        }

        return $cases + [
            'non-leap day' => [['start' => '2025-02-28T08:00:00+00:00', 'end' => '2025-02-28T12:00:00+00:00',
                'legLocalStart' => 'Feb 29 08:00', 'legLocalEnd' => 'Feb 28 12:00',
                'dutyLocalStart' => 'Feb 28 06:00', 'dutyLocalEnd' => 'Feb 28 12:30']],
            'duty report after departure' => [['dutyLocalStart' => 'Jun 16 09:00']],
            'duty longer than thirty hours by a minute' => [['dutyLocalStart' => 'Jun 15 06:29']],
            'duty report several days early' => [['dutyLocalStart' => 'Jun 13 06:00']],
            'duty release several days late' => [['dutyLocalEnd' => 'Jun 19 12:30']],
            'OCR correction needs two days' => [['dutyLocalEnd' => 'Jun 14 12:30']],
            'local departure too far from UTC' => [['legLocalStart' => 'Jun 14 08:00']],
            'local arrival too far from UTC' => [['legLocalEnd' => 'Jun 19 12:00']],
            'local UTC offset beyond fourteen hours' => [['legLocalEnd' => 'Jun 17 02:01']],
            'flight ends before it starts' => [['end' => '2026-06-16T07:00:00+00:00']],
            'flight has zero duration' => [['end' => '2026-06-16T08:00:00+00:00']],
        ];
    }
}
