<?php

namespace Tests\Unit;

use App\Services\FlightDutyCalendarEventService;
use Tests\TestCase;

class FlightDutyCalendarEventServiceTest extends TestCase
{
    public function test_it_builds_a_duty_calendar_event_from_flight_and_local_duty_offsets(): void
    {
        $event = app(FlightDutyCalendarEventService::class)->buildFromFlight([
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
        $event = app(FlightDutyCalendarEventService::class)->buildFromFlight([
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
        $event = app(FlightDutyCalendarEventService::class)->buildFromFlight([
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
        $event = app(FlightDutyCalendarEventService::class)->buildFromFlight([
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
}
