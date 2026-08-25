<?php

namespace Tests\Unit;

use App\Exceptions\FlightPlanDataConflictException;
use App\Services\FlightPlan\Extractor\FlightInitExtractor;
use PHPUnit\Framework\TestCase;

class FlightInitExtractorTest extends TestCase
{
    public function test_it_extracts_the_explicit_acars_init_date_instead_of_the_flight_date(): void
    {
        $result = $this->extractor()->extract($this->fixture('acars-init-date'));

        $this->assertSame([
            'section_present' => true,
            'acars_init_date' => '11',
            'fms_initial_altitude' => null,
        ], $result['data']);
        $this->assertArrayHasKey('flight_init_takeoff_landing_report', $result['source_fragments']);
        $this->assertStringContainsString('ACARS INIT DATE 11', $result['source_fragments']['flight_init_takeoff_landing_report']);
        $this->assertStringNotContainsString('FLIGHT DATE 12MAY26', $result['source_fragments']['flight_init_takeoff_landing_report']);
    }

    public function test_it_supports_flattened_text_and_preserves_a_leading_zero(): void
    {
        $text = str_replace('ACARS INIT DATE 11', 'ACARS INIT DATE 01', $this->fixture('acars-init-date'));
        $flattened = str_replace("\n", ' ', $text);

        $this->assertSame('01', $this->extractor()->extract($flattened)['data']['acars_init_date']);
    }

    public function test_it_extracts_the_date_before_a_same_line_acars_takeoff_request(): void
    {
        $text = <<<'TEXT'
TAKEOFF AND LANDING REPORT CKS 0256 KLAX-RKSI 25MAY26
TLR-22 SEQ-94208476C 24MAY26 2155Z
A/C N774CK B777-200F GE90-110BL
ACARS INIT DATE 25 ACARS TAKEOFF REQUEST 2111Z-0911Z
TEXT;
        $result = $this->extractor()->extract($text);
        $wrappedDate = $this->extractor()->extract(str_replace('DATE 25', "DATE\n25", $text));

        $this->assertSame([
            'section_present' => true,
            'acars_init_date' => '25',
            'fms_initial_altitude' => null,
        ], $result['data']);
        $this->assertSame('25', $wrappedDate['data']['acars_init_date']);

        $invalidUtf8 = $this->extractor()->extract(str_replace('GE90-110BL', "GE90-110BL \x96", $text));

        $this->assertSame('25', $invalidUtf8['data']['acars_init_date']);

        $concatenatedLabel = $this->extractor()->extract(str_replace(
            "GE90-110BL\nACARS INIT DATE 25",
            'GE90-110BLACARS INIT DATE   25',
            $text,
        ));

        $this->assertSame('25', $concatenatedLabel['data']['acars_init_date']);
    }

    public function test_it_distinguishes_an_absent_report_from_a_report_without_a_valid_init_date(): void
    {
        $absent = $this->extractor()->extract('ROUTE KDFW DCT RKSI');
        $missing = $this->extractor()->extract('TAKEOFF AND LANDING REPORT CKS 0524 KDFW-RKSI 11MAY26');
        $invalid = $this->extractor()->extract('TAKEOFF AND LANDING REPORT ACARS INIT DATE 32');

        $this->assertSame(['section_present' => false, 'acars_init_date' => null, 'fms_initial_altitude' => null], $absent['data']);
        $this->assertSame(['section_present' => true, 'acars_init_date' => null, 'fms_initial_altitude' => null], $missing['data']);
        $this->assertSame(['section_present' => true, 'acars_init_date' => null, 'fms_initial_altitude' => null], $invalid['data']);
        $this->assertSame([], $absent['source_fragments']);
        $this->assertSame([], $missing['source_fragments']);
        $this->assertSame([], $invalid['source_fragments']);
    }

    public function test_it_deduplicates_agreement_and_rejects_conflicting_init_dates(): void
    {
        $fixture = $this->fixture('acars-init-date');
        $duplicate = $this->extractor()->extract($fixture."\n".$fixture);

        $this->assertSame('11', $duplicate['data']['acars_init_date']);

        $this->expectException(FlightPlanDataConflictException::class);
        $this->expectExceptionMessage('Conflicting flight release values were found for ACARS init date.');

        $this->extractor()->extract($fixture."\n".str_replace('ACARS INIT DATE 11', 'ACARS INIT DATE 12', $fixture));
    }

    public function test_it_extracts_the_fms_initial_altitude_from_repeated_destination_summaries(): void
    {
        $summary = 'DEST RKSI 033.4 01.48 290 0896 P078';
        $result = $this->extractor()->extract($summary."\nCOPIED PAGE\nWIND".$summary);

        $this->assertSame('F290', $result['data']['fms_initial_altitude']);
        $this->assertTrue($result['data']['section_present']);
        $this->assertSame($summary, $result['source_fragments']['flight_init_fms_initial_altitude']);
    }

    public function test_it_rejects_conflicting_fms_initial_altitudes(): void
    {
        $this->expectException(FlightPlanDataConflictException::class);
        $this->expectExceptionMessage('Conflicting flight release values were found for FMS initial altitude.');

        $this->extractor()->extract(
            'DEST RKSI 033.4 01.48 290 0896 P078 DEST RKSI 033.4 01.48 310 0896 P078',
        );
    }

    private function extractor(): FlightInitExtractor
    {
        return new FlightInitExtractor;
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__."/../Fixtures/FlightPlan/flight-init/{$name}.txt");

        $this->assertIsString($contents);

        return $contents;
    }
}
