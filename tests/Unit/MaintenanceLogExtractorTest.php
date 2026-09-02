<?php

namespace Tests\Unit;

use App\Exceptions\FlightPlanDataConflictException;
use App\Services\FlightPlan\Extractor\MaintenanceLogExtractor;
use PHPUnit\Framework\TestCase;

class MaintenanceLogExtractorTest extends TestCase
{
    public function test_it_distinguishes_an_explicit_empty_log_from_an_absent_section(): void
    {
        $empty = $this->extractor()->extract($this->fixture('no-items'));
        $absent = $this->extractor()->extract('ROUTE KLAX DCT RKSI');

        $this->assertTrue($empty['data']['section_present']);
        $this->assertSame([], $empty['data']['items']);
        $this->assertArrayHasKey('maintenance_log', $empty['source_fragments']);

        $this->assertFalse($absent['data']['section_present']);
        $this->assertSame([], $absent['data']['items']);
        $this->assertSame([], $absent['source_fragments']);
    }

    public function test_it_extracts_one_item_without_owning_shared_crew_data(): void
    {
        $result = $this->extractor()->extract($this->fixture('one-item'));

        $this->assertSame([
            [
                'type' => 'MEL',
                'number' => '28-22-01',
                'description' => 'Center tank override pump inoperative.',
                'reference' => '1042',
                'status' => 'OPEN',
                'limitations' => null,
                'procedures' => null,
            ],
        ], $result['data']['items']);
        $this->assertArrayNotHasKey('crew_members', $result['data']);
        $this->assertArrayHasKey('maintenance_item_1', $result['source_fragments']);
    }

    public function test_it_extracts_multiple_item_types(): void
    {
        $result = $this->extractor()->extract($this->fixture('multiple-items'));

        $this->assertCount(3, $result['data']['items']);
        $this->assertSame(['MEL', 'CDL', 'DMI'], array_column($result['data']['items'], 'type'));
        $this->assertSame('MC-771', $result['data']['items'][2]['reference']);
        $this->assertSame('MONITOR', $result['data']['items'][2]['status']);
    }

    public function test_it_normalizes_wrapped_descriptions_without_inventing_optional_notes(): void
    {
        $item = $this->extractor()->extract($this->fixture('wrapped-description'))['data']['items'][0];

        $this->assertSame(
            'Left main landing gear brake temperature sensor indication is intermittent during taxi operations.',
            $item['description'],
        );
        $this->assertSame('DMI-2099', $item['reference']);
        $this->assertNull($item['limitations']);
        $this->assertNull($item['procedures']);
    }

    public function test_it_extracts_flattened_operational_limitations_and_procedures(): void
    {
        $item = $this->extractor()->extract($this->fixture('operational-limitations'))['data']['items'][0];

        $this->assertSame('Right weather radar channel inoperative.', $item['description']);
        $this->assertSame('Dispatch only under the source-listed weather radar restriction.', $item['limitations']);
        $this->assertSame('Accomplish the source-listed operations procedure before departure.', $item['procedures']);
    }

    public function test_it_extracts_operational_mel_cdl_records_with_single_letter_markers_and_unlabelled_descriptions(): void
    {
        $result = $this->extractor()->extract($this->fixture('operational-mel-cdl'));
        $items = $result['data']['items'];

        $this->assertTrue($result['data']['section_present']);
        $this->assertCount(8, $items);
        $this->assertSame(
            ['MEL', 'MEL', 'MEL', 'MEL', 'MEL', 'CDL', 'CDL', 'CDL'],
            array_column($items, 'type'),
        );
        $this->assertSame(
            ['100172093', '100172116', '100172117', '100289476', '100312468', '100316318', '100316338', '100316345'],
            array_column($items, 'reference'),
        );
        $this->assertSame('33-21-01-02', $items[0]['number']);
        $this->assertSame('33-21-01-02', $items[2]['number']);
        $this->assertSame(
            'CABIN INTERIOR ILLUMINATION-SUPERNUMERARY COMPARTMENT LIGHTS 777F/777ERSF',
            $items[0]['description'],
        );
        $this->assertSame(
            'POTABLE WATER SYSTEMS INOPERATIVE COMPONENTS DEACTIVATED (M)',
            $items[1]['description'],
        );
        $this->assertSame(
            'INBOARD FLAP TRACK FLAPERON FAIRING SEALS (PERF)',
            $items[7]['description'],
        );
        $this->assertStringNotContainsString('PAGE 2 OF 197', $items[6]['description']);
        $this->assertStringNotContainsString('PASSED RAIM', $items[7]['description']);
    }

    public function test_it_extracts_all_mels_with_variable_number_segments_across_a_page_break(): void
    {
        $result = $this->extractor()->extract($this->fixture('zsof-variable-mel-numbers'));
        $items = $result['data']['items'];

        $this->assertTrue($result['data']['section_present']);
        $this->assertCount(8, $items);
        $this->assertSame(['NEF', ...array_fill(0, 7, 'MEL')], array_column($items, 'type'));
        $this->assertSame([
            '25-20-1-NEF-16',
            '23-27-1-2',
            '47-11-1-1',
            '25-25-3-3',
            '22-11-7',
            '27-02-3',
            '22-99-02',
            '22-99-01',
        ], array_column($items, 'number'));
        $this->assertSame([
            '100224958',
            '100230493',
            '100230523',
            '100230529',
            '100230535',
            '100230536',
            '100230537',
            '100230538',
        ], array_column($items, 'reference'));
        $this->assertSame([
            'MISCELLANEOUS INTERIOR TRIM (NON-STRUCTURAL PANELS AND MOLDINGS)',
            'DATA COMMUNICATION MANAGEMENT SYSTEM (ETOPS) ACPT/CANC/RJCT SWITCH LIGHTS',
            'NITROGEN GENERATION SYSTEM (NGS) NITROGEN GENERATION PERFORMANCE',
            'SUPERNUMERARY SEATS (777F) LEG RESTS (M)',
            'AUTOMATIC LANDING SYSTEM (AUTOLAND) (LMP) AUTOMATIC LANDING SYSTEM (AUTOLAND)',
            'PRIMARY FLIGHT COMPUTER CHANNELS (LMP) (M)',
            'LMP STATUS - AIRCRAFT DOWNGRADED TO CAT II (M)',
            'LMP STATUS - AIRCRAFT DOWNGRADED TO CAT I (M)',
        ], array_column($items, 'description'));
        $this->assertStringStartsWith('MEL/CDL', $result['source_fragments']['maintenance_log']);
        $this->assertStringNotContainsString('MAINTENANCE WRITE UP IS', $result['source_fragments']['maintenance_log']);
        $this->assertStringNotContainsString('PAGE 2 OF 37', $items[6]['description']);
        $this->assertStringNotContainsString('PASSED RAIM', $items[7]['description']);
        $this->assertArrayHasKey('maintenance_item_8', $result['source_fragments']);
        $this->assertArrayNotHasKey('maintenance_item_9', $result['source_fragments']);
    }

    public function test_it_does_not_treat_maintenance_prose_as_a_log_section(): void
    {
        $result = $this->extractor()->extract('THESE ARE NOT A VALID FAULT AND A MAINTENANCE WRITE UP IS NOT REQUIRED.');

        $this->assertFalse($result['data']['section_present']);
        $this->assertSame([], $result['data']['items']);
        $this->assertSame([], $result['source_fragments']);
    }

    public function test_it_rejects_operational_numbers_outside_confirmed_segment_boundaries(): void
    {
        $result = $this->extractor()->extract(<<<'TEXT'
MEL/CDL
M 25-20-1DMI 100000001 CONFIRMED NUMBER
M 25-2-1DMI 100000002 SECOND SEGMENT TOO SHORT
M 25-20-ABCDE DMI 100000003 THIRD SEGMENT TOO LONG
M 25-20-1-A-B-CDMI 100000004 TOO MANY SUFFIX SEGMENTS
PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION
TEXT);

        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('25-20-1', $result['data']['items'][0]['number']);
        $this->assertSame('100000001', $result['data']['items'][0]['reference']);
    }

    public function test_it_ignores_malformed_numbers_and_deduplicates_identical_items(): void
    {
        $result = $this->extractor()->extract(<<<'TEXT'
MAINTENANCE LOG
MEL ??? | DESCRIPTION: Invalid item.
MEL 25-20-1-NEF-16 | STATUS: OPEN | DESCRIPTION: Interior trim panel deferred.
MEL 25-20-1-NEF-16 | STATUS: OPEN | DESCRIPTION: Interior trim panel deferred.
MEL 25-20-1-NEF2 | STATUS: OPEN | DESCRIPTION: A near match remains MEL.
END MAINTENANCE LOG
TEXT);

        $this->assertCount(2, $result['data']['items']);
        $this->assertSame('25-20-1-NEF-16', $result['data']['items'][0]['number']);
        $this->assertSame('NEF', $result['data']['items'][0]['type']);
        $this->assertSame('MEL', $result['data']['items'][1]['type']);
    }

    public function test_it_rejects_conflicting_duplicate_items(): void
    {
        $this->expectException(FlightPlanDataConflictException::class);
        $this->expectExceptionMessage('Conflicting flight release values were found for maintenance item NEF 25-20-1-NEF-16.');

        $this->extractor()->extract(<<<'TEXT'
MAINTENANCE LOG
MEL 25-20-1-NEF-16 | STATUS: OPEN | DESCRIPTION: First description.
MEL 25-20-1-NEF-16 | STATUS: OPEN | DESCRIPTION: Conflicting description.
END MAINTENANCE LOG
TEXT);
    }

    private function extractor(): MaintenanceLogExtractor
    {
        return new MaintenanceLogExtractor;
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__."/../Fixtures/FlightPlan/maintenance-log/{$name}.txt");

        $this->assertIsString($contents);

        return $contents;
    }
}
