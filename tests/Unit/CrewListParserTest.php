<?php

namespace Tests\Unit;

use App\Services\CrewListParser;
use Tests\TestCase;

class CrewListParserTest extends TestCase
{
    public function test_it_parses_noisy_crew_lines_into_structured_members(): void
    {
        $crew = app(CrewListParser::class)->parse([
            'Name Crew Pos Base',
            'w Jesper Brandt Jensen 71022 (OP ete)',
            'w Julio Rodriguez Batista 71559 FO EYW',
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
}
