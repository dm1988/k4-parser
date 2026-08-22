<?php

namespace Tests\Unit;

use App\Services\FlightPlan\Extractor\FlightCrewExtractor;
use App\Services\Schedule\Extractor\CrewListParser;
use PHPUnit\Framework\TestCase;

class FlightCrewExtractorTest extends TestCase
{
    public function test_it_extracts_and_deduplicates_members_from_a_bounded_flight_crew_section(): void
    {
        $result = $this->extractor()->extract(<<<'TEXT'
CREW LIST
Name Crew Pos Base
Alex Morgan 4827 CP YIP
Jordan Lee 93614 FO YIP
Alex Morgan 4827 CP YIP
MAINTENANCE LOG
MEL 28-22-01 | DESCRIPTION: Center tank override pump inoperative.
TEXT);

        $this->assertSame([
            ['name' => 'Alex Morgan', 'role' => 'CP', 'base' => 'YIP', 'employee_number' => '4827'],
            ['name' => 'Jordan Lee', 'role' => 'FO', 'base' => 'YIP', 'employee_number' => '93614'],
        ], $result['data']);
        $this->assertArrayHasKey('flight_crew', $result['source_fragments']);
        $this->assertStringNotContainsString('28-22-01', $result['source_fragments']['flight_crew']);
    }

    public function test_it_supports_the_short_crew_heading_and_returns_empty_data_when_absent(): void
    {
        $present = $this->extractor()->extract(<<<'TEXT'
CREW
Alex Morgan 4827 CP YIP
ROUTE
KLAX DCT RKSI
TEXT);
        $absent = $this->extractor()->extract('ROUTE KLAX DCT RKSI');

        $this->assertSame('Alex Morgan', $present['data'][0]['name']);
        $this->assertSame([], $absent['data']);
        $this->assertSame([], $absent['source_fragments']);
    }

    public function test_it_extracts_six_members_from_an_id_first_flight_release_manifest(): void
    {
        $result = $this->extractor()->extract($this->fixture('release-manifest'));

        $this->assertSame([
            ['name' => 'MORGAN A', 'role' => 'PIC', 'base' => null, 'employee_number' => '4387'],
            ['name' => 'RIVERA D', 'role' => 'SIC/FO', 'base' => null, 'employee_number' => '72914'],
            ['name' => 'FOSTER B', 'role' => 'IRP', 'base' => null, 'employee_number' => '73521'],
            ['name' => 'MCCULLOUGH M', 'role' => 'IRP', 'base' => null, 'employee_number' => '73642'],
            ['name' => 'BENNETT B', 'role' => 'MX', 'base' => null, 'employee_number' => '5826'],
            ['name' => 'GARCIA T', 'role' => 'LM', 'base' => null, 'employee_number' => '1957'],
        ], $result['data']);
        $this->assertArrayHasKey('flight_crew', $result['source_fragments']);
        $this->assertStringNotContainsString('FUEL SUMMARY', $result['source_fragments']['flight_crew']);

    }

    private function extractor(): FlightCrewExtractor
    {
        return new FlightCrewExtractor(new CrewListParser);
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../Fixtures/FlightPlan/crew/'.$name.'.txt');

        $this->assertNotFalse($contents);

        return $contents;
    }
}
