<?php

namespace Tests\Unit;

use App\Services\FlightPlan\FlightPlanTextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FlightPlanTextNormalizerTest extends TestCase
{
    #[DataProvider('pageLabels')]
    public function test_it_removes_page_labels_and_preserves_content_and_page_boundaries(string $source, string $expected): void
    {
        $normalized = FlightPlanTextNormalizer::normalize($source);

        $this->assertSame($expected, $normalized);
        $this->assertSame($expected, FlightPlanTextNormalizer::normalize($normalized));
    }

    /** @return array<string, array{string, string}> */
    public static function pageLabels(): array
    {
        return [
            'ordinary label' => ['BEFORE KALITTA BRIEF PAGE 4 OF 83 AFTER', "BEFORE \f AFTER"],
            'standalone label' => ['BEFORE PAGE 4 OF 83 AFTER', "BEFORE \f AFTER"],
            'fused labels and content' => ['EFBKALITTA BRIEF PAGE 4 OF 83PAGE 4 OF 83FRQ', "EFB\f\fFRQ"],
            'wrapped mixed case label' => ["BEFORE Kalitta\tBrief\r\nPage 4\nOf 83 AFTER", "BEFORE \f AFTER"],
            'null bytes' => ["FLIGHT\x00 PLAN", 'FLIGHT PLAN'],
            'route tokens and operational header' => ['KALITTA AIR TRIP 123 DCT PAGE DCT BRIEF DCT PAGE2 DCT MADII7', 'KALITTA AIR TRIP 123 DCT PAGE DCT BRIEF DCT PAGE2 DCT MADII7'],
        ];
    }
}
