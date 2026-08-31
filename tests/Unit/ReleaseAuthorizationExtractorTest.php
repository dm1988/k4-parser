<?php

namespace Tests\Unit;

use App\Enums\OperationsSpecification;
use App\Exceptions\FlightPlanDataConflictException;
use App\Services\FlightPlan\Extractor\ReleaseAuthorizationExtractor;
use PHPUnit\Framework\TestCase;

class ReleaseAuthorizationExtractorTest extends TestCase
{
    public function test_it_extracts_explicit_b43_and_b44_signatures(): void
    {
        $extractor = new ReleaseAuthorizationExtractor;

        $b43 = $extractor->extract('Dispatch RELEASED IAW OPS SPEC B043 approved.');
        $b44 = $extractor->extract('Dispatch RELEASED IAW OPS SPEC B044 approved.');

        $this->assertSame(OperationsSpecification::B43->value, $b43['data']['operations_specification']);
        $this->assertSame('RELEASED IAW OPS SPEC B043', $b43['source_fragments']['release_authorization']);
        $this->assertSame(OperationsSpecification::B44->value, $b44['data']['operations_specification']);
        $this->assertSame('RELEASED IAW OPS SPEC B044', $b44['source_fragments']['release_authorization']);
    }

    public function test_it_accepts_case_whitespace_and_flattened_pdf_variations(): void
    {
        $extractor = new ReleaseAuthorizationExtractor;

        $lineBreaks = $extractor->extract("released\n IAW\tOPS  SPEC\nB044");
        $flattened = $extractor->extract('RELEASEDIAWOPSSPECB043');

        $this->assertSame(OperationsSpecification::B44->value, $lineBreaks['data']['operations_specification']);
        $this->assertSame('released IAW OPS SPEC B044', $lineBreaks['source_fragments']['release_authorization']);
        $this->assertSame(OperationsSpecification::B43->value, $flattened['data']['operations_specification']);
    }

    public function test_it_returns_unknown_for_missing_partial_or_incidental_references(): void
    {
        $extractor = new ReleaseAuthorizationExtractor;

        foreach ([
            'No Operations Specification signature is present.',
            'Refer to B044 for background information.',
            'RELEASED IAW OPS SPEC',
            'RELEASED IAW OPS SPEC B044A',
            'UNRELEASED IAW OPS SPEC B044',
        ] as $text) {
            $result = $extractor->extract($text);

            $this->assertSame(OperationsSpecification::Unknown->value, $result['data']['operations_specification']);
            $this->assertSame([], $result['source_fragments']);
        }
    }

    public function test_it_allows_repeated_matching_signatures(): void
    {
        $result = (new ReleaseAuthorizationExtractor)->extract(
            'RELEASED IAW OPS SPEC B044 duplicate RELEASED IAW OPS SPEC B044',
        );

        $this->assertSame(OperationsSpecification::B44->value, $result['data']['operations_specification']);
    }

    public function test_it_rejects_conflicting_signatures(): void
    {
        $this->expectException(FlightPlanDataConflictException::class);
        $this->expectExceptionMessage('Conflicting flight release values were found for Operations Specification.');

        (new ReleaseAuthorizationExtractor)->extract(
            'RELEASED IAW OPS SPEC B043 and RELEASED IAW OPS SPEC B044',
        );
    }
}
