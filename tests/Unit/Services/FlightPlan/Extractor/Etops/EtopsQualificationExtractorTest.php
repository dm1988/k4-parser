<?php

namespace Tests\Unit\Services\FlightPlan\Extractor\Etops;

use App\Enums\EtopsApplicability;
use App\Services\FlightPlan\Extractor\Etops\EtopsQualificationExtractor;
use PHPUnit\Framework\TestCase;

class EtopsQualificationExtractorTest extends TestCase
{
    public function test_it_confirms_etops_and_extracts_the_source_rating(): void
    {
        $result = (new EtopsQualificationExtractor)->extract(
            "DISPATCH DATA\nETOPS 180  ETOPS ALTERNATE AIRPORTS\nPANC PACD",
        );

        $this->assertSame([
            'section_present' => true,
            'applicability' => 'confirmed_etops',
            'rating_minutes' => 180,
        ], $result['data']);
        $this->assertSame('ETOPS 180 ETOPS ALTERNATE AIRPORTS', $result['source_fragments']['etops_qualification']);
    }

    public function test_it_accepts_parser_whitespace_and_a_210_minute_rating(): void
    {
        $result = (new EtopsQualificationExtractor)->extract("ETOPS\t210\nETOPS   ALTERNATE AIRPORTS");

        $this->assertSame(210, $result['data']['rating_minutes']);
        $this->assertSame('confirmed_etops', $result['data']['applicability']);
    }

    public function test_it_accepts_an_airport_concatenated_to_the_heading(): void
    {
        $result = (new EtopsQualificationExtractor)->extract(
            'N36430E127299ETOPS 180  ETOPS ALTERNATE AIRPORTSSFO/KSFO  SAN FRANCISCO INTL',
        );

        $this->assertSame(180, $result['data']['rating_minutes']);
        $this->assertSame('confirmed_etops', $result['data']['applicability']);
        $this->assertSame('ETOPS 180 ETOPS ALTERNATE AIRPORTS', $result['source_fragments']['etops_qualification']);
    }

    public function test_it_rejects_a_heading_embedded_in_alphabetic_text(): void
    {
        $result = (new EtopsQualificationExtractor)->extract(
            'INVALIDETOPS 180 ETOPS ALTERNATE AIRPORTSSFO/KSFO',
        );

        $this->assertNull($result['data']['rating_minutes']);
        $this->assertSame([], $result['source_fragments']);
    }

    public function test_it_does_not_infer_etops_from_unrelated_etops_content(): void
    {
        $extractor = new EtopsQualificationExtractor;

        foreach (['ETOPS ETP1 scenario and alternate notes', 'ETOPS 000 ETOPS ALTERNATE AIRPORTS'] as $text) {
            $result = $extractor->extract($text);

            $this->assertSame([
                'section_present' => false,
                'applicability' => 'unknown',
                'rating_minutes' => null,
            ], $result['data']);
            $this->assertSame([], $result['source_fragments']);
        }
    }

    public function test_it_owns_explicit_and_operational_etops_applicability_detection(): void
    {
        $extractor = new EtopsQualificationExtractor;

        $this->assertSame(EtopsApplicability::ConfirmedEtops, $extractor->applicability('ETOPS FLIGHT: YES'));
        $this->assertSame(EtopsApplicability::ConfirmedNonEtops, $extractor->applicability('ETOPS FLIGHT: NO'));
        $this->assertSame(EtopsApplicability::ConfirmedNonEtops, $extractor->applicability('NO ETOPS'));
        $this->assertSame(EtopsApplicability::ConfirmedEtops, $extractor->applicability('ETOPS 60/180'));
        $this->assertSame(EtopsApplicability::Unknown, $extractor->applicability('ETOPS information unavailable'));
    }
}
