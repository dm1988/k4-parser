<?php

namespace Tests\Unit\Services\FlightPlan\Extractor\Etops;

use App\Services\FlightPlan\Extractor\Etops\EtopsRouteExtractor;
use PHPUnit\Framework\TestCase;

class EtopsRouteExtractorTest extends TestCase
{
    public function test_it_extracts_deduplicated_equal_time_points_and_boundary_coordinates(): void
    {
        $result = (new EtopsRouteExtractor)->extract(<<<'TEXT'
ETP1  KSFO-PACD  N45  43.7  W143  53.1  ALL ENGINE/DECOMPRESSION/LRC
ETP1  KSFO-PACD  N45  43.7  W143  53.1  ALL ENGINE/DECOMPRESSION/LRC
ETP1  KSFO-PACD  N46 01.0  W144 35.5  1EO/DRIFTDOWN/84M/320KIAS
ETP2  PACD-RJSS  N51 48.6  E164 12.8  ALL ENGINE/DECOMPRESSION/LRC
N40 31.1 W131 22.6(EENT)
N45 19.3 E151 36.4(EEXP)
TEXT);

        $this->assertSame([
            [
                'label' => 'ETP1',
                'airports' => 'KSFO-PACD',
                'coordinates' => 'N45 43.7 W143 53.1',
                'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
            ],
            [
                'label' => 'ETP2',
                'airports' => 'PACD-RJSS',
                'coordinates' => 'N51 48.6 E164 12.8',
                'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
            ],
        ], $result['data']['etps']);
        $this->assertSame('N40 31.1 W131 22.6', $result['data']['eent_coordinates']);
        $this->assertSame('N45 19.3 E151 36.4', $result['data']['eexp_coordinates']);
    }

    public function test_it_returns_empty_route_facts_without_supported_etops_evidence(): void
    {
        $result = (new EtopsRouteExtractor)->extract(
            'ETP1 KSFO-PACD N46 01.0 W144 35.5 1EO/DRIFTDOWN/84M/320KIAS',
        );

        $this->assertSame([
            'etps' => [],
            'eent_coordinates' => null,
            'eexp_coordinates' => null,
        ], $result['data']);
        $this->assertSame([], $result['source_fragments']);
    }
}
