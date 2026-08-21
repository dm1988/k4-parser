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
            ['name' => 'Alex Morgan', 'role' => 'CP', 'base' => 'YIP'],
            ['name' => 'Jordan Lee', 'role' => 'FO', 'base' => 'YIP'],
        ], $result['data']);
        $this->assertArrayHasKey('flight_crew', $result['source_fragments']);
        $this->assertStringNotContainsString('28-22-01', $result['source_fragments']['flight_crew']);
        $encodedData = json_encode($result['data'], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('4827', $encodedData);
        $this->assertStringNotContainsString('93614', $encodedData);
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

    private function extractor(): FlightCrewExtractor
    {
        return new FlightCrewExtractor(new CrewListParser);
    }
}
