<?php

namespace Tests\Unit;

use App\Services\FlightPlan\Extractor\FlightCrewExtractor;
use App\Services\Schedule\Extractor\CrewListParser;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Parser;

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
            ['name' => 'Alex Morgan', 'role' => 'CP', 'base' => 'YIP', 'employee_number' => '4827', 'high_mins' => false],
            ['name' => 'Jordan Lee', 'role' => 'FO', 'base' => 'YIP', 'employee_number' => '93614', 'high_mins' => false],
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
            ['name' => 'MORGAN A', 'role' => 'PIC', 'base' => null, 'employee_number' => '4387', 'high_mins' => false],
            ['name' => 'RIVERA D', 'role' => 'SIC/FO', 'base' => null, 'employee_number' => '72914', 'high_mins' => false],
            ['name' => 'FOSTER B', 'role' => 'IRP', 'base' => null, 'employee_number' => '73521', 'high_mins' => false],
            ['name' => 'MCCULLOUGH M', 'role' => 'IRP', 'base' => null, 'employee_number' => '73642', 'high_mins' => false],
            ['name' => 'BENNETT B', 'role' => 'MX', 'base' => null, 'employee_number' => '5826', 'high_mins' => false],
            ['name' => 'GARCIA T', 'role' => 'LM', 'base' => null, 'employee_number' => '1957', 'high_mins' => false],
        ], $result['data']);
        $this->assertArrayHasKey('flight_crew', $result['source_fragments']);
        $this->assertStringNotContainsString('FUEL SUMMARY', $result['source_fragments']['flight_crew']);
    }

    public function test_it_ignores_a_solar_forecast_inside_the_crew_section(): void
    {
        $result = $this->extractor()->extract(<<<'TEXT'
CREW LIST
:Product: 3-Day Forecast
:Issued: 2026 May 19 1230 UTC
A. NOAA Geomagnetic Activity Observation and Forecast
The greatest expected 3 hr Kp for May 19-May 21 2026 is 4.67 (NOAA Scale G1).
NOAA Kp index breakdown May 19-May 21 2026
Solar Radiation Storm Forecast for May 19-May 21 2026
Radio Blackout Forecast for May 19-May 21 2026
MAINTENANCE LOG
ETOPS 180 ETOPS ALTERNATE AIRPORTS
SFO/KSFO SAN FRANCISCO INTL N37371W122225
121-91 FLIGHT RELEASE I.F.R
4387 PIC MORGAN A
72914 SIC/FO RIVERA D
ADDNTL
CAPT
73521 IRP FOSTER B
73642 IRP MCCULLOUGH M
MX LM
ACM ACM
TEXT);

        $this->assertSame([
            ['name' => 'MORGAN A', 'role' => 'PIC', 'base' => null, 'employee_number' => '4387', 'high_mins' => false],
            ['name' => 'RIVERA D', 'role' => 'SIC/FO', 'base' => null, 'employee_number' => '72914', 'high_mins' => false],
            ['name' => 'FOSTER B', 'role' => 'IRP', 'base' => null, 'employee_number' => '73521', 'high_mins' => false],
            ['name' => 'MCCULLOUGH M', 'role' => 'IRP', 'base' => null, 'employee_number' => '73642', 'high_mins' => false],
        ], $result['data']);
        $this->assertStringNotContainsString('Solar Radiation', $result['source_fragments']['flight_crew']);
        $this->assertStringContainsString('4387 PIC MORGAN A', $result['source_fragments']['flight_crew']);
    }

    public function test_it_retains_the_final_relief_pilot_before_flattened_role_placeholders(): void
    {
        $result = $this->extractor()->extract($this->fixture('release-manifest-trailing-placeholders'));

        $this->assertSame([
            ['name' => 'THATCHER A', 'role' => 'PIC', 'base' => null, 'employee_number' => '4827', 'high_mins' => false],
            ['name' => 'GONZALEZ D', 'role' => 'SIC/FO', 'base' => null, 'employee_number' => '93614', 'high_mins' => false],
            ['name' => 'MCCLINTOCK A', 'role' => 'IRP', 'base' => null, 'employee_number' => '84726', 'high_mins' => false],
        ], $result['data']);
        $this->assertStringContainsString('84726 IRP MCCLINTOCK A', $result['source_fragments']['flight_crew']);
        $this->assertStringNotContainsString('FUEL SUMMARY', $result['source_fragments']['flight_crew']);
    }

    public function test_it_extracts_manifest_annotations_without_including_them_in_names(): void
    {
        $result = $this->extractor()->extract(<<<'TEXT'
121-91 FLIGHT RELEASE I.F.R
4387 PIC PAYNE R ADDNTL
72914 SIC/FO GONZALEZ D IRP
73521 IRP FERGUSON S HIGH MINS
73521 IRP FERGUSON S
FUEL SUMMARY
TEXT);

        $this->assertSame(['PAYNE R', 'GONZALEZ D', 'FERGUSON S'], array_column($result['data'], 'name'));
        $this->assertSame([false, false, true], array_column($result['data'], 'high_mins'));
    }

    public function test_it_keeps_name_boundaries_when_empty_roles_precede_high_mins(): void
    {
        $result = $this->extractor()->extract(<<<'TEXT'
121-91 FLIGHT RELEASE I.F.R
70897 PIC BALSKE B
73315 IRP SINHA A IRP MX LM ACM HIGH MINS
FUEL SUMMARY
TEXT);

        $this->assertSame([
            ['name' => 'BALSKE B', 'role' => 'PIC', 'base' => null, 'employee_number' => '70897', 'high_mins' => false],
            ['name' => 'SINHA A', 'role' => 'IRP', 'base' => null, 'employee_number' => '73315', 'high_mins' => true],
        ], $result['data']);
        $this->assertStringContainsString('SINHA A IRP MX LM ACM HIGH MINS', $result['source_fragments']['flight_crew']);
    }

    public function test_it_extracts_a_named_acm_without_an_employee_number_and_deduplicates_the_manifest(): void
    {
        $manifest = '72480 IRP SINHA A IRP MX LM ACM YATES R ACM ACM CIRCLE THE APPROPRIATE STATUS';
        $result = $this->extractor()->extract("121-91 FLIGHT RELEASE I.F.R\n{$manifest}\n{$manifest}\nFUEL SUMMARY");

        $this->assertSame([
            ['name' => 'SINHA A', 'role' => 'IRP', 'base' => null, 'employee_number' => '72480', 'high_mins' => false],
            ['name' => 'YATES R', 'role' => 'ACM', 'base' => null, 'employee_number' => null, 'high_mins' => false],
        ], $result['data']);
        $this->assertStringContainsString('ACM YATES R', $result['source_fragments']['flight_crew']);
    }

    public function test_it_prefers_the_bounded_flattened_release_manifest_over_notam_crew_text(): void
    {
        $manifest = '121-91 FLIGHT RELEASE I.F.R 70388 PIC MACDONALD T 72480 SIC/FO SINHA A ADDNTL 71022 CAPT BRANDT-JENSEN J 73425 IRP TAYLOR K IRP MX LM ACM ACM ACM ACM CIRCLE THE APPROPRIATE STATUS (BASIC / STANDARD / DOUBLE)RELEASE TIME 1219';
        $notam = "CREW\nRUNWAY CLOSURES\nAC PHONE +852 2910 AC ISO\nWEATHER";

        foreach (["{$manifest}\n{$manifest}\n{$notam}", "{$notam}\n{$manifest}"] as $text) {
            $result = $this->extractor()->extract($text);

            $this->assertSame([
                ['name' => 'MACDONALD T', 'role' => 'PIC', 'base' => null, 'employee_number' => '70388', 'high_mins' => false],
                ['name' => 'SINHA A', 'role' => 'SIC/FO', 'base' => null, 'employee_number' => '72480', 'high_mins' => false],
                ['name' => 'BRANDT-JENSEN J', 'role' => 'CAPT', 'base' => null, 'employee_number' => '71022', 'high_mins' => false],
                ['name' => 'TAYLOR K', 'role' => 'IRP', 'base' => null, 'employee_number' => '73425', 'high_mins' => false],
            ], $result['data']);
            $this->assertStringNotContainsString('PHONE', $result['source_fragments']['flight_crew']);
            $this->assertStringNotContainsString('RELEASE TIME', $result['source_fragments']['flight_crew']);
            $this->assertStringNotContainsString('CIRCLE', $result['source_fragments']['flight_crew']);
        }
    }

    public function test_it_does_not_extract_role_like_text_outside_a_crew_or_release_section(): void
    {
        $result = $this->extractor()->extract("NOTAM\n4387 PIC CONTACT A\n72914 SIC/FO CONTACT B\nWEATHER");

        $this->assertSame([], $result['data']);
        $this->assertSame([], $result['source_fragments']);
    }

    public function test_it_does_not_search_notam_prose_for_crew_rows_after_a_crew_heading(): void
    {
        $result = $this->extractor()->extract("CREW\nRUNWAY CLOSURES\nAC PHONE +852 2910 AC ISO\nWEATHER");

        $this->assertSame([], $result['data']);
        $this->assertSame([], $result['source_fragments']);
    }

    public function test_it_tries_a_later_release_manifest_when_the_first_one_has_no_members(): void
    {
        $result = $this->extractor()->extract(<<<'TEXT'
121-91 FLIGHT RELEASE I.F.R
ACM ACM
RELEASE TIME 1219
121-91 FLIGHT RELEASE I.F.R
70388 PIC MACDONALD T
72480 SIC/FO SINHA A
FUEL SUMMARY
TEXT);

        $this->assertSame(['MACDONALD T', 'SINHA A'], array_column($result['data'], 'name'));
    }

    public function test_it_keeps_a_single_manifest_member_and_excludes_records_after_the_release_footer(): void
    {
        $result = $this->extractor()->extract(<<<'TEXT'
121-91 FLIGHT RELEASE I.F.R
70388 PIC MACDONALD T
RELEASE TIME 1219
73425 IRP CONTACT A
72914 SIC/FO CONTACT B
TEXT);

        $this->assertSame(['MACDONALD T'], array_column($result['data'], 'name'));
        $this->assertStringNotContainsString('CONTACT', $result['source_fragments']['flight_crew']);
    }

    public function test_it_extracts_the_reported_private_pdf_crew_manifest(): void
    {
        $path = __DIR__.'/../../storage/app/private/flight_releases/CKS024201RJGG.pdf';

        if (! is_file($path)) {
            $this->markTestSkipped('The private flight-release PDF fixture is not available.');
        }

        $text = (new Parser)->parseFile($path)->getText();
        $result = $this->extractor()->extract($text);

        $this->assertSame(['MACDONALD T', 'SINHA A', 'BRANDT-JENSEN J', 'TAYLOR K'], array_column($result['data'], 'name'));
        $this->assertSame(['70388', '72480', '71022', '73425'], array_column($result['data'], 'employee_number'));
        $this->assertSame(['PIC', 'SIC/FO', 'CAPT', 'IRP'], array_column($result['data'], 'role'));
        $this->assertStringNotContainsString('PHONE', $result['source_fragments']['flight_crew']);
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
