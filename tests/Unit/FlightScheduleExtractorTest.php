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
        $this->assertSame([
            [
                'direction' => 'departure',
                'airport' => 'KLAX',
                'instant_utc' => '2026-05-25T23:40:00+00:00',
                'source_time' => '2340Z',
                'tolerance_minutes' => null,
            ],
            [
                'direction' => 'arrival',
                'airport' => 'RKSI',
                'instant_utc' => '2026-05-26T03:10:00+00:00',
                'source_time' => '0310Z',
                'tolerance_minutes' => null,
            ],
        ], $result['data']['slots']);
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
        $this->assertSame([], $result['data']['slots']);
    }

    public function test_it_sorts_multiple_slots_by_complete_instant_and_preserves_source_order_for_ties(): void
    {
        $result = (new FlightScheduleExtractor)->extract(<<<'TEXT'
SHETD 23.20Z/25 ETA 02.50Z
APPROVED SLOT TIMES: ARR RKSI @ 0310Z DEP KONT @ 2340Z DEP KLAX @ 2340Z ARR PANC @ 0260Z
******************************************************************
TEXT, '2026-05-25');

        $this->assertSame(['KONT', 'KLAX', 'RKSI'], array_column($result['data']['slots'], 'airport'));
        $this->assertSame(['2340Z', '2340Z', '0310Z'], array_column($result['data']['slots'], 'source_time'));
    }

    public function test_it_preserves_inline_and_multiline_slot_windows_and_raw_text(): void
    {
        $inline = (new FlightScheduleExtractor)->extract(
            "SHETD 14.00Z/25 ETA 18.00Z\nAPPROVED SLOT TIMES: ARR RKSI @ 1520Z (+/- 30 MIN)",
            '2026-05-25',
        );

        $this->assertSame(30, $inline['data']['slots'][0]['tolerance_minutes']);
        $this->assertSame('APPROVED SLOT TIMES: ARR RKSI @ 1520Z (+/- 30 MIN)', $inline['data']['slot_source_text']);

        $complex = (new FlightScheduleExtractor)->extract(<<<'TEXT'
SHETD 02.00Z/25 ETA 06.00Z
*** APPROVED SLOT TIMES:
   - ZSOF: 0330Z DEP
   - RKSI: 0600Z +- 30 MIN
 *** NO MORE THAN 30 MINUTES EARLY ARRIVAL TO ICN ***
TEXT, '2026-05-25');

        $this->assertSame(['departure', 'arrival'], array_column($complex['data']['slots'], 'direction'));
        $this->assertSame([null, 30], array_column($complex['data']['slots'], 'tolerance_minutes'));
        $this->assertStringContainsString('NO MORE THAN 30 MINUTES EARLY ARRIVAL TO ICN', $complex['data']['slot_source_text']);
    }

    public function test_it_deduplicates_repeated_slots_and_stops_raw_text_at_operational_section_boundaries(): void
    {
        foreach (['ETOPS', 'MEL/CDL'] as $boundary) {
            $result = (new FlightScheduleExtractor)->extract(<<<TEXT
SHETD 14.00Z/25 ETA 18.00Z
APPROVED SLOT TIMES: ARR RKSI @ 1520Z (+/- 30 MIN)
APPROVED SLOT TIMES: ARR RKSI @ 1520Z (+/- 30 MIN)
*** {$boundary} CONTENT THAT MUST NOT BE CAPTURED ***
TEXT, '2026-05-25');

            $this->assertCount(1, $result['data']['slots']);
            $this->assertSame('RKSI', $result['data']['slots'][0]['airport']);
            $this->assertStringNotContainsString($boundary, $result['data']['slot_source_text']);
        }
    }
}
