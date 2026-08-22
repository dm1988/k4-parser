<?php

namespace Tests\Unit;

use App\Enums\CrewPosition;
use App\Services\Schedule\Extractor\CrewListParser;
use Tests\TestCase;

class CrewListParserTest extends TestCase
{
    public function test_it_parses_noisy_crew_lines_into_structured_members(): void
    {
        $crew = app(CrewListParser::class)->parse([
            'Name Crew Pos Base',
            'w Jesper Brandt Jensen 71022 (OP ete)',
            'Xx Julio Rodriguez Batista 71559 FO EYW',
            'aXe Cameron Stovold 71835 DH LAX',
            '* David Gonzalez 72860 INZe) AUS',
        ]);

        $this->assertCount(4, $crew);
        $this->assertSame('Jesper Brandt Jensen', $crew[0]['name']);
        $this->assertSame('71022', $crew[0]['employee_id']);
        $this->assertSame('OP', $crew[0]['role']);
        $this->assertNull($crew[0]['base']);
        $this->assertFalse($crew[0]['deadheading']);

        $this->assertSame('Julio Rodriguez Batista', $crew[1]['name']);
        $this->assertSame('71559', $crew[1]['employee_id']);
        $this->assertSame('FO', $crew[1]['role']);
        $this->assertSame('EYW', $crew[1]['base']);
        $this->assertFalse($crew[1]['deadheading']);

        $this->assertSame('Cameron Stovold', $crew[2]['name']);
        $this->assertSame('71835', $crew[2]['employee_id']);
        $this->assertSame('DH', $crew[2]['role']);
        $this->assertSame('LAX', $crew[2]['base']);
        $this->assertTrue($crew[2]['deadheading']);

        $this->assertSame('David Gonzalez', $crew[3]['name']);
        $this->assertSame('72860', $crew[3]['employee_id']);
        $this->assertNull($crew[3]['role']);
        $this->assertSame('AUS', $crew[3]['base']);
        $this->assertFalse($crew[3]['deadheading']);
    }

    public function test_it_returns_crew_counts_with_parsed_members(): void
    {
        $summary = app(CrewListParser::class)->parseWithSummary([
            'w Jesper Brandt Jensen 71022 (OP ete)',
            'w Julio Rodriguez Batista 71559 FO EYW',
            'aXe Cameron Stovold 71835 DH LAX',
            '* David Gonzalez 72860 INZe) AUS',
        ]);

        $this->assertCount(4, $summary['crew']);
        $this->assertSame(4, $summary['crew_count']);
        $this->assertSame(3, $summary['operating_crew_count']);
        $this->assertSame(1, $summary['deadheading_crew_count']);
    }

    public function test_it_parses_observer_role_from_an_embedded_crew_line(): void
    {
        $crew = app(CrewListParser::class)->parse([
            'Ww Tiyal Bell 4325 OB CLD',
        ]);

        $this->assertCount(1, $crew);
        $this->assertSame(CrewPosition::Observer->value, $crew[0]['role']);
    }

    public function test_it_excludes_an_observer_from_operating_crew_count(): void
    {
        $summary = app(CrewListParser::class)->parseWithSummary([
            'Ww Tiyal Bell 4325 OB CLD',
        ]);

        $this->assertSame(1, $summary['crew_count']);
        $this->assertSame(0, $summary['operating_crew_count']);
        $this->assertSame(0, $summary['deadheading_crew_count']);
        $this->assertFalse($summary['crew'][0]['deadheading']);
    }

    public function test_it_parses_four_digit_employee_ids_and_lm_positions(): void
    {
        $summary = app(CrewListParser::class)->parseWithSummary([
            'Crew list',
            'Name Crew Pos Base',
            'aXe Scott Ferguson 70984 cP DHN',
            'aXe Giwoong Shim 72368 FO PHX',
            'x Hani El Mir 70326 FME MLB',
            'aXe Renato Pezzulo 73441 AFO ney',
            '* David Gonzalez 72860 AFO INOS',
            'aXe Henry Garcia Santos 2847 LM YIP',
        ]);

        $this->assertSame(6, $summary['crew_count']);
        $this->assertSame(6, $summary['operating_crew_count']);
        $this->assertSame(0, $summary['deadheading_crew_count']);

        $this->assertSame('CP', $summary['crew'][0]['role']);
        $this->assertSame('FME', $summary['crew'][2]['role']);
        $this->assertSame('Henry Garcia Santos', $summary['crew'][5]['name']);
        $this->assertSame('2847', $summary['crew'][5]['employee_id']);
        $this->assertSame('2847', $summary['crew'][5]['crew_id']);
        $this->assertSame('LM', $summary['crew'][5]['role']);
        $this->assertSame('YIP', $summary['crew'][5]['base']);
        $this->assertFalse($summary['crew'][5]['deadheading']);
    }

    public function test_it_types_flight_release_manifest_positions(): void
    {
        $this->assertSame(CrewPosition::PilotInCommand, CrewPosition::tryFrom('PIC'));
        $this->assertSame(CrewPosition::SecondInCommand, CrewPosition::tryFrom('SIC/FO'));
        $this->assertSame(CrewPosition::AdditionalCaptain, CrewPosition::tryFrom('ADDNTL CAPT'));
        $this->assertSame(CrewPosition::InternationalReliefPilot, CrewPosition::tryFrom('IRP'));
        $this->assertSame(CrewPosition::MaintenancePersonnel, CrewPosition::tryFrom('MX'));
        $this->assertSame(CrewPosition::Loadmaster, CrewPosition::tryFrom('LM'));
        $this->assertSame(CrewPosition::AdditionalCrewMember, CrewPosition::tryFrom('ACM'));
    }

    public function test_it_parses_id_first_manifest_rows_and_ignores_role_only_placeholders(): void
    {
        $summary = app(CrewListParser::class)->parseWithSummary([
            '4387 PIC MORGAN A',
            '72914 SIC/FO RIVERA D',
            'ADDNTL',
            'CAPT',
            '73521 IRP FOSTER B',
            '73642 IRP MCCULLOUGH M',
            '5826 MX BENNETT B 1957 LM GARCIA T',
            'ACM ACM',
            'ACM ACM',
        ]);

        $this->assertSame(6, $summary['crew_count']);
        $this->assertSame(6, $summary['operating_crew_count']);
        $this->assertSame(0, $summary['deadheading_crew_count']);
        $this->assertSame(
            ['MORGAN A', 'RIVERA D', 'FOSTER B', 'MCCULLOUGH M', 'BENNETT B', 'GARCIA T'],
            array_column($summary['crew'], 'name'),
        );
        $this->assertSame(
            ['PIC', 'SIC/FO', 'IRP', 'IRP', 'MX', 'LM'],
            array_column($summary['crew'], 'role'),
        );
    }

    public function test_it_removes_a_flattened_additional_captain_heading_from_the_preceding_name(): void
    {
        $crew = app(CrewListParser::class)->parseReleaseManifestLine(
            '72914 SIC/FO GONZALEZ D ADDNTL CAPT',
        );

        $this->assertCount(1, $crew);
        $this->assertSame('GONZALEZ D', $crew[0]['name']);
        $this->assertSame(CrewPosition::SecondInCommand->value, $crew[0]['role']);

        $flattenedCrew = app(CrewListParser::class)->parseReleaseManifestLine(
            '72914 SIC/FO GONZALEZ D ADDNTL CAPT 73521 IRP FOSTER B',
        );

        $this->assertSame(['GONZALEZ D', 'FOSTER B'], array_column($flattenedCrew, 'name'));
        $this->assertSame(['SIC/FO', 'IRP'], array_column($flattenedCrew, 'role'));

        $additionalCaptain = app(CrewListParser::class)->parseReleaseManifestLine(
            '73332 ADDNTL CAPT DE LA CRUZ J',
        );

        $this->assertSame('DE LA CRUZ J', $additionalCaptain[0]['name']);
        $this->assertSame(CrewPosition::AdditionalCaptain->value, $additionalCaptain[0]['role']);
    }
}
