<?php

namespace Tests\Unit;

use App\Exceptions\FlightPlanDataConflictException;
use App\Services\FlightPlan\Extractor\FlightScheduleExtractor;
use PHPUnit\Framework\TestCase;

class FlightScheduleExtractorTest extends TestCase
{
    public function test_it_extracts_canonical_utc_schedule_and_slots(): void
    {
        $result = (new FlightScheduleExtractor)->extract(<<<'TEXT'
SHETD 23.20Z/25 ETA 02.50Z
APPROVED SLOT TIMES: DEP KLAX @ 2340Z ARR RKSI @ 0310Z
******************************************************************
(FPL-CKS256-IS-B77L/H-SDE2-KLAX2320-N0487F340 DCT TEST-RKSI1210)
TEXT, '2026-05-25');

        $this->assertSame('2026-05-25T23:20:00+00:00', $result['data']['etd_utc']);
        $this->assertSame('2026-05-26T02:50:00+00:00', $result['data']['eta_utc']);
        $this->assertSame([
            '2026-05-25T23:40:00+00:00',
            '2026-05-26T03:10:00+00:00',
        ], $result['data']['slot_times_utc']);
        $this->assertNull($result['data']['block_duration']);
    }

    public function test_it_rejects_a_conflicting_fpl_departure_time(): void
    {
        $this->expectException(FlightPlanDataConflictException::class);
        $this->expectExceptionMessage('Conflicting flight release values were found for scheduled departure time.');

        (new FlightScheduleExtractor)->extract(
            'SHETD 02.20Z/25 ETA 14.50Z (FPL-CKS256-IS-B77L/H-SDE2-KLAX0230-N0487F340 DCT TEST-RKSI1210)',
            '2026-05-25',
        );
    }

    public function test_it_leaves_schedule_null_without_a_confirmed_flight_date(): void
    {
        $result = (new FlightScheduleExtractor)->extract('SHETD 02.20Z/25 ETA 14.50Z', null);

        $this->assertNull($result['data']['etd_utc']);
        $this->assertNull($result['data']['eta_utc']);
        $this->assertSame([], $result['data']['slot_times_utc']);
    }
}
