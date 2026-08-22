<?php

namespace Tests\Unit;

use App\Exceptions\FlightPlanDataConflictException;
use App\Services\FlightPlan\Extractor\EnvelopeExtractor;
use PHPUnit\Framework\TestCase;

class EnvelopeExtractorTest extends TestCase
{
    public function test_it_extracts_the_selected_takeoff_result_with_explicit_units_and_provenance(): void
    {
        $result = $this->extractor()->extract($this->fixture('selected-result'));

        $this->assertSame([
            'section_present' => true,
            'source_type' => 'takeoff_landing_report',
            'report_reference' => 'TLR-30 SEQ-48273190 25MAY26 0115Z',
            'airport' => 'KLAX',
            'planned_runway' => '25R',
            'outside_air_temperature_celsius' => 18.0,
            'wind' => '250M08',
            'qnh_inches_mercury' => 29.92,
            'maximum_runway_takeoff_weight' => ['amount' => 768000, 'unit' => 'lb'],
            'flap_setting' => '15',
            'anti_ice' => false,
            'v1_knots' => 151,
            'rotate_knots' => 158,
            'v2_knots' => 164,
            'planned_takeoff_weight' => ['amount' => 612400, 'unit' => 'lb'],
            'maximum_field_takeoff_weight' => ['amount' => 766000, 'unit' => 'lb'],
            'source_warnings' => ['32-41-03 - SOURCE BRAKE MESSAGE'],
        ], $result['data']);
        $this->assertArrayHasKey('envelope_takeoff_landing_report', $result['source_fragments']);
    }

    public function test_it_supports_multiline_and_flattened_source_text_and_preserves_boundary_values(): void
    {
        $multiline = $this->extractor()->extract($this->fixture('multiline-result'))['data'];
        $flattened = $this->extractor()->extract(str_replace("\n", ' ', $this->fixture('selected-result')))['data'];

        $this->assertSame(-5.0, $multiline['outside_air_temperature_celsius']);
        $this->assertTrue($multiline['anti_ice']);
        $this->assertSame([
            '32-41-03 - SOURCE BRAKE MESSAGE',
            '27-51-01 - SOURCE FLAP MESSAGE',
        ], $multiline['source_warnings']);
        $this->assertSame('KLAX', $flattened['airport']);
        $this->assertSame(['amount' => 612400, 'unit' => 'lb'], $flattened['planned_takeoff_weight']);
    }

    public function test_it_keeps_missing_limits_null_without_discarding_the_calculated_result(): void
    {
        $data = $this->extractor()->extract($this->fixture('missing-limits'))['data'];

        $this->assertTrue($data['section_present']);
        $this->assertNull($data['maximum_runway_takeoff_weight']);
        $this->assertNull($data['maximum_field_takeoff_weight']);
        $this->assertSame(['amount' => 612400, 'unit' => 'lb'], $data['planned_takeoff_weight']);
    }

    public function test_it_supports_operational_intersection_runways_alphanumeric_sequences_and_explicit_no_warnings(): void
    {
        $text = str_replace(
            [
                'SEQ-48273190',
                'KLAX 25R 18.0',
                'RMKS 32-41-03 - SOURCE BRAKE MESSAGE',
            ],
            [
                'SEQ-48273190C',
                'KLAX 25R-E957F 18.0',
                'RMKS NONE',
            ],
            $this->fixture('selected-result'),
        );

        $data = $this->extractor()->extract($text)['data'];

        $this->assertSame('25R-E957F', $data['planned_runway']);
        $this->assertSame('TLR-30 SEQ-48273190C 25MAY26 0115Z', $data['report_reference']);
        $this->assertSame([], $data['source_warnings']);
    }

    public function test_it_distinguishes_an_absent_report_from_a_report_without_a_supported_result(): void
    {
        $absent = $this->extractor()->extract('ROUTE KLAX DCT RKSI');
        $unsupported = $this->extractor()->extract('TAKEOFF AND LANDING REPORT CKS 0256 KLAX-RKSI');

        $this->assertFalse($absent['data']['section_present']);
        $this->assertTrue($unsupported['data']['section_present']);
        $this->assertNull($unsupported['data']['planned_takeoff_weight']);
        $this->assertSame([], $absent['source_fragments']);
        $this->assertSame([], $unsupported['source_fragments']);
    }

    public function test_source_evidence_tracks_the_report_that_produced_the_result(): void
    {
        $result = $this->extractor()->extract(
            "TAKEOFF AND LANDING REPORT UNSUPPORTED\n".$this->fixture('selected-result'),
        );

        $this->assertStringContainsString('TLR-30 SEQ-48273190', $result['source_fragments']['envelope_takeoff_landing_report']);
        $this->assertStringNotContainsString('UNSUPPORTED', $result['source_fragments']['envelope_takeoff_landing_report']);
    }

    public function test_it_deduplicates_identical_results_and_rejects_conflicting_reports(): void
    {
        $fixture = $this->fixture('selected-result');

        $duplicate = $this->extractor()->extract($fixture."\n".$fixture);
        $this->assertSame(612400, $duplicate['data']['planned_takeoff_weight']['amount']);

        $this->expectException(FlightPlanDataConflictException::class);
        $this->expectExceptionMessage('Conflicting flight release values were found for takeoff and landing report envelope result.');

        $this->extractor()->extract($fixture."\n".str_replace('6124 7660', '6125 7660', $fixture));
    }

    private function extractor(): EnvelopeExtractor
    {
        return new EnvelopeExtractor;
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__."/../Fixtures/FlightPlan/envelope/{$name}.txt");

        $this->assertIsString($contents);

        return $contents;
    }
}
