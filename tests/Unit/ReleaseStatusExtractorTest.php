<?php

namespace Tests\Unit;

use App\Enums\ReleaseStatus;
use App\Services\FlightPlan\Extractor\ReleaseStatusExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReleaseStatusExtractorTest extends TestCase
{
    private const string WARNING = 'PRELIMINARY FLIGHT PLAN - PAYLOAD ESTIMATED - DO NOT FUEL AIRCRAFT';

    #[DataProvider('supportedHeaders')]
    public function test_detects_the_complete_bounded_warning_and_retains_only_the_original_match(string $text, string $warning): void
    {
        $result = (new ReleaseStatusExtractor)->extract($text);

        $this->assertSame(ReleaseStatus::Preliminary, $result->status);
        $this->assertSame($warning, $result->sourceWarning);
    }

    /** @return array<string, array{string, string}> */
    public static function supportedHeaders(): array
    {
        $wrapped = "preliminary\n flight\tplan – payload\n estimated — do not fuel aircraft";

        return [
            'plain header' => [self::WARNING, self::WARNING],
            'decorative asterisks' => ['*** '.self::WARNING.' ***', self::WARNING],
            'fused flight header' => ['********'.self::WARNING.'********KALITTA AIR TRIP 123', self::WARNING],
            'case wrapping and dashes' => [$wrapped, $wrapped],
            'no spaces around dashes' => [str_replace(' - ', '-', self::WARNING), str_replace(' - ', '-', self::WARNING)],
            'OCR appended after native pages' => ["NATIVE PAGE\nFINAL DEST KORD\n".self::WARNING."\nKALITTA AIR", self::WARNING],
            'duplicates and authorization' => ['***'.self::WARNING."***\nRELEASED IAW OPS SPEC B044\n".strtolower(self::WARNING), self::WARNING],
            'unicode whitespace and hyphens' => ["PRELIMINARY\u{00a0}FLIGHT PLAN ‐ PAYLOAD ESTIMATED − DO NOT FUEL AIRCRAFT", "PRELIMINARY\u{00a0}FLIGHT PLAN ‐ PAYLOAD ESTIMATED − DO NOT FUEL AIRCRAFT"],
        ];
    }

    #[DataProvider('unmarkedText')]
    public function test_does_not_infer_finality_or_match_incidental_or_partial_text(string $text): void
    {
        $result = (new ReleaseStatusExtractor)->extract($text);

        $this->assertSame(ReleaseStatus::NotMarkedPreliminary, $result->status);
        $this->assertNull($result->sourceWarning);
        $this->assertNull($result->display()['label']);
    }

    /** @return array<string, array{string}> */
    public static function unmarkedText(): array
    {
        return [
            'empty' => [''],
            'dispatcher note' => ['Dispatcher: preliminary numbers for review'],
            'incomplete warning' => ['PRELIMINARY FLIGHT PLAN - PAYLOAD ESTIMATED'],
            'authorization and destinations' => ['FINAL DEST KORD RELEASED IAW OPS SPEC B043 RELEASE REVISION 9'],
            'b44' => ['RELEASED IAW OPS SPEC B044'],
            'filename' => ['storage/preliminary/CKS-PRELIMINARY-FLIGHT-PLAN.pdf'],
            'unbounded quoted prose' => ['Dispatcher quoted '.self::WARNING.' in a note.'],
            'word prefix' => ['NOT'.self::WARNING],
            'word suffix' => [self::WARNING.'S'],
            'punctuation substitution' => ['PRELIMINARY FLIGHT PLAN / PAYLOAD ESTIMATED / DO NOT FUEL AIRCRAFT'],
            'fuzzy substitution' => ['PRELIMINARY FLIGHT PLAN - PAYLOAD ESTIMATE - DO NOT FUEL AIRCRAFT'],
        ];
    }
}
