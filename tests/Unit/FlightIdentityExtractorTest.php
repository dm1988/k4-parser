<?php

namespace Tests\Unit;

use App\Exceptions\FlightPlanDataConflictException;
use App\Services\FlightPlan\Extractor\FlightIdentityExtractor;
use PHPUnit\Framework\TestCase;

class FlightIdentityExtractorTest extends TestCase
{
    public function test_it_extracts_and_corroborates_release_identity(): void
    {
        $result = (new FlightIdentityExtractor)->extract(<<<'TEXT'
KALITTA AIR TRIP 109546 RECALL 62930 N774CK B777-200F 05/25/26
SHETD 02.20Z/25 ETA 14.50Z CKS256
(FPL-CKS256-IS
-B77L/H-SDE2
-KLAX0220
-N0487F340 DCT TEST
-RKSI1210
-PBN/A1 DOF/260525 REG/N774CK)
TEXT);

        $this->assertSame([
            'flight_number' => 'CKS256',
            'trip_number' => '109546',
            'recall_number' => '62930',
            'aircraft_type' => 'B777-200F',
            'tail_number' => 'N774CK',
            'flight_date' => '2026-05-25',
            'release_revision' => null,
        ], $result['data']);
        $this->assertArrayHasKey('identity_header', $result['source_fragments']);
        $this->assertArrayHasKey('icao_flight_plan', $result['source_fragments']);
    }

    public function test_it_rejects_conflicting_header_and_fpl_identity(): void
    {
        $this->expectException(FlightPlanDataConflictException::class);
        $this->expectExceptionMessage('Conflicting flight release values were found for flight number.');

        (new FlightIdentityExtractor)->extract(<<<'TEXT'
KALITTA AIR TRIP 109546 RECALL 62930 N774CK B777-200F 05/25/26
ETA 14.50Z CKS256
(FPL-CKS257-IS-B77L/H-SDE2-KLAX0220-N0487F340 DCT TEST-RKSI1210-PBN/A1 DOF/260525 REG/N774CK)
TEXT);
    }

    public function test_it_rejects_non_five_digit_recall_numbers_without_losing_other_header_fields(): void
    {
        foreach (['1234', '123456'] as $recallNumber) {
            $result = (new FlightIdentityExtractor)->extract(<<<TEXT
KALITTA AIR TRIP 109546 RECALL {$recallNumber} N774CK B777-200F 05/25/26
ETA 14.50Z CKS256
(FPL-CKS256-IS-B77L/H-SDE2-KLAX0220-N0487F340 DCT TEST-RKSI1210-PBN/A1 DOF/260525 REG/N774CK)
TEXT);

            $this->assertNull($result['data']['recall_number']);
            $this->assertSame('109546', $result['data']['trip_number']);
            $this->assertSame('N774CK', $result['data']['tail_number']);
            $this->assertArrayHasKey('identity_header', $result['source_fragments']);
        }
    }
}
