<?php

namespace Tests\Unit\DTOs;

use App\DTOs\ScheduleData;
use Tests\TestCase;

class ScheduleDataTest extends TestCase
{
    public function test_it_keeps_utc_and_local_operational_times_explicit(): void
    {
        $schedule = new ScheduleData(
            etdUtc: '2026-08-21T12:00:00+00:00',
            etdLocal: 'Aug 21 08:00',
            etaUtc: '2026-08-21T16:00:00+00:00',
            etaLocal: 'Aug 21 12:00',
            blockDuration: '4:00h',
            reportTimeUtc: '2026-08-21T10:00:00+00:00',
            reportTimeLocal: 'Aug 21 06:00',
            slotTimesUtc: ['2026-08-21T12:15:00+00:00'],
            slotTimesLocal: ['Aug 21 08:15'],
        );

        $this->assertSame('2026-08-21T12:00:00+00:00', $schedule->etdUtc);
        $this->assertSame('Aug 21 08:00', $schedule->etdLocal);
        $this->assertSame('2026-08-21T16:00:00+00:00', $schedule->etaUtc);
        $this->assertSame('Aug 21 12:00', $schedule->etaLocal);
        $this->assertSame('4:00h', $schedule->blockDuration);
        $this->assertSame(['2026-08-21T12:15:00+00:00'], $schedule->slotTimesUtc);
        $this->assertSame(['Aug 21 08:15'], $schedule->slotTimesLocal);
    }

    public function test_it_normalizes_legacy_flight_schedule_fields(): void
    {
        $schedule = ScheduleData::fromArray([
            'start' => '2026-08-21T12:00:00+00:00',
            'end' => '2026-08-21T16:00:00+00:00',
            'block_time' => '4:00h',
            'leg_local_start' => 'Aug 21 08:00',
            'leg_local_end' => 'Aug 21 12:00',
            'duty_utc_start' => '2026-08-21T10:00:00+00:00',
            'duty_local_start' => 'Aug 21 06:00',
            'duty_utc_end' => '2026-08-21T17:00:00+00:00',
            'duty_local_end' => 'Aug 21 13:00',
            'slot_times_utc' => [' 1215Z ', ''],
            'slot_times_local' => [' 0815 '],
        ]);

        $this->assertSame('2026-08-21T12:00:00+00:00', $schedule->etdUtc);
        $this->assertSame('2026-08-21T16:00:00+00:00', $schedule->etaUtc);
        $this->assertSame('4:00h', $schedule->blockDuration);
        $this->assertSame('Aug 21 08:00', $schedule->etdLocal);
        $this->assertSame('Aug 21 12:00', $schedule->etaLocal);
        $this->assertSame('2026-08-21T10:00:00+00:00', $schedule->reportTimeUtc);
        $this->assertSame('Aug 21 06:00', $schedule->reportTimeLocal);
        $this->assertSame('2026-08-21T17:00:00+00:00', $schedule->dutyEndUtc);
        $this->assertSame('Aug 21 13:00', $schedule->dutyEndLocal);
        $this->assertSame(['1215Z'], $schedule->slotTimesUtc);
        $this->assertSame(['0815'], $schedule->slotTimesLocal);
    }

    public function test_it_round_trips_without_ambiguous_time_keys(): void
    {
        $schedule = ScheduleData::fromArray([
            'etdUtc' => '1200Z',
            'etdLocal' => '0800',
            'etaUtc' => '1600Z',
            'etaLocal' => '1200',
            'blockDuration' => '4:00h',
            'reportTimeUtc' => '1000Z',
            'reportTimeLocal' => '0600',
            'slotTimesUtc' => ['1215Z', '1230Z'],
            'slotTimesLocal' => ['0815', '0830'],
        ]);

        $this->assertSame($schedule->toArray(), ScheduleData::fromArray($schedule->toArray())->toArray());
        $this->assertArrayNotHasKey('etd', $schedule->toArray());
        $this->assertArrayNotHasKey('eta', $schedule->toArray());
        $this->assertArrayNotHasKey('blockTime', $schedule->toArray());
    }
}
