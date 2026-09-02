<?php

namespace Tests\Unit\Services\FlightPlan\Extractor;

use App\Services\FlightPlan\Extractor\GeneralDeclarationExtractor;
use PHPUnit\Framework\TestCase;

class GeneralDeclarationExtractorTest extends TestCase
{
    public function test_it_detects_a_representative_general_declaration_page(): void
    {
        $result = (new GeneralDeclarationExtractor)->extract(<<<'TEXT'
            General Declaration
            (Outward/Inward)
            Owner or Operator:
            K4
            Marks of Nationality and Registration:
            N774CK
            Departure from:
            Los Angeles
            United States
            Flight No:
            K4256
            Date:
            24May2026
            Arrival At:
            TEXT);

        $this->assertTrue($result['data']['section_present']);
        $this->assertArrayHasKey('general_declaration_signature', $result['source_fragments']);
        $this->assertStringStartsWith('General Declaration', $result['source_fragments']['general_declaration_signature']);
        $this->assertStringEndsWith('Arrival At:', $result['source_fragments']['general_declaration_signature']);
    }

    public function test_it_tolerates_flattened_labels_and_irregular_whitespace(): void
    {
        $text = 'GENERAL   DECLARATION ( Outward / Inward ) Owner or Operator:K4 '
            .'Marks of Nationality and Registration:N774CK Departure from:Los Angeles '
            .'Flight No:K4256 Date:24May2026 Arrival At:Incheon';

        $result = (new GeneralDeclarationExtractor)->extract($text);

        $this->assertTrue($result['data']['section_present']);
    }

    public function test_it_detects_the_deidentified_dumped_page_with_adjacent_flight_number_and_date_labels(): void
    {
        $text = '**KALITTA BRIEF PAGE 150 OF 150** **PAGE 150 OF 150** '
            .'**General Declaration** **(Outward/Inward)** **Owner or Operator: K4** '
            .'**Marks of Nationality and Registration:N000XX** **Departure from:** **Los Angeles** '
            .'**United States** **Flight No:K4000Date: 24May2026** **Arrival At:\tSeoul**';

        $result = (new GeneralDeclarationExtractor)->extract($text);

        $this->assertTrue($result['data']['section_present']);
    }

    public function test_it_detects_labels_extracted_in_two_column_reading_order(): void
    {
        $text = 'General Declaration (Outward/Inward) Owner or Operator: K4 '
            .'Marks of Nationality and Registration: N000XX Flight No: K4000 Date: 24May2026 '
            .'Departure from: Los Angeles Arrival At: Seoul';

        $result = (new GeneralDeclarationExtractor)->extract($text);

        $this->assertTrue($result['data']['section_present']);
    }

    public function test_it_reports_a_missing_page(): void
    {
        $result = (new GeneralDeclarationExtractor)->extract('Operational flight release without a declaration page.');

        $this->assertFalse($result['data']['section_present']);
        $this->assertSame([], $result['source_fragments']);
    }

    public function test_it_rejects_incidental_general_declaration_text_without_field_structure(): void
    {
        $text = 'Document index: General Declaration is available from station operations. '
            .'The flight number and date are listed elsewhere in the release.';

        $result = (new GeneralDeclarationExtractor)->extract($text);

        $this->assertFalse($result['data']['section_present']);
        $this->assertSame([], $result['source_fragments']);
    }

    public function test_it_rejects_labels_outside_the_bounded_section_window(): void
    {
        $text = 'General Declaration '.str_repeat('unrelated content ', 180)
            .'(Outward/Inward) Owner or Operator: K4 Marks of Nationality and Registration: N774CK '
            .'Departure from: Los Angeles Flight No: K4256 Date: 24May2026 Arrival At: Incheon';

        $result = (new GeneralDeclarationExtractor)->extract($text);

        $this->assertFalse($result['data']['section_present']);
    }
}
