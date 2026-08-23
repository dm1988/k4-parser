<?php

namespace Tests\Unit;

use App\Services\FlightPlan\Extractor\WaypointExtractor;
use PHPUnit\Framework\TestCase;

class WaypointExtractorTest extends TestCase
{
    public function test_it_extracts_ordered_waypoints_from_the_computed_flight_plan(): void
    {
        $result = (new WaypointExtractor)->extract($this->fixture('computed-flight-plan.txt'));

        $this->assertSame([
            ['coordinate' => 'N51 25.9 E012 16.1', 'identifier' => '51259N', 'time' => '001', 'total_time' => '00.01', 'remaining_fuel' => '1853'],
            ['coordinate' => 'N51 36.5 E012 11.5', 'identifier' => 'DP550', 'time' => '002', 'total_time' => '00.03', 'remaining_fuel' => '1825'],
            ['coordinate' => 'N51 42.5 E012 05.3', 'identifier' => 'PENEM', 'time' => '002', 'total_time' => '00.05', 'remaining_fuel' => '1813'],
            ['coordinate' => 'N51 51.0 E011 50.3', 'identifier' => 'ODLUN', 'time' => '002', 'total_time' => '00.07', 'remaining_fuel' => '1797'],
            ['coordinate' => 'N51 52.9 E011 45.9', 'identifier' => '-EDWW', 'time' => null, 'total_time' => '00.07', 'remaining_fuel' => '1793'],
            ['coordinate' => 'N52 03.5 E011 21.0', 'identifier' => 'EMBOX', 'time' => '003', 'total_time' => '00.10', 'remaining_fuel' => '1774'],
            ['coordinate' => 'N52 05.9 E011 03.5', 'identifier' => '-EDVV', 'time' => null, 'total_time' => '00.12', 'remaining_fuel' => '1765'],
            ['coordinate' => 'N52 07.7 E010 49.7', 'identifier' => 'POVEL', 'time' => '003', 'total_time' => '00.13', 'remaining_fuel' => '1757'],
            ['coordinate' => 'N51 51.4 E007 42.5', 'identifier' => 'HMM', 'time' => '013', 'total_time' => '00.28', 'remaining_fuel' => '1702'],
            ['coordinate' => 'N51 50.4 E006 25.9', 'identifier' => '-EHAA', 'time' => null, 'total_time' => '00.34', 'remaining_fuel' => '1683'],
        ], $result['data']);

        $this->assertArrayHasKey('computed_flight_plan_waypoints', $result['source_fragments']);
        $this->assertStringNotContainsString('TOC 0021', $result['source_fragments']['computed_flight_plan_waypoints']);
        $this->assertStringNotContainsString('FUEL SUMMARY', $result['source_fragments']['computed_flight_plan_waypoints']);
    }

    public function test_it_requires_the_computed_flight_plan_headers(): void
    {
        $result = (new WaypointExtractor)->extract(<<<'TEXT'
N51 36.5 E012 11.5
DP550 0011 340 CLB 18/009 P012 266 CLB 002 ... ... 0035 1825 ....
3985 339 CLB 278 00.03 ... ... .... .... 1515
TEXT);

        $this->assertSame([], $result['data']);
        $this->assertSame([], $result['source_fragments']);
    }

    public function test_it_preserves_duplicate_identifiers_and_leading_zeroes(): void
    {
        $text = <<<'TEXT'
IDENT DIST MC FL WIND CMP TAS/MAC TIME ETA ATA TBO FRMG EFB
FRQ DTGO MH W/S OAT G/S T/TME REV REM ABO AFOB DSTN
N01 02.3 E004 05.6
FIX01 0001 001 CLB 01/002 P003 004 CLB 005 ... ... 0006 0007 ....
0008 009 CLB 010 00.11 ... ... .... .... 0012
N02 03.4 E005 06.7
FIX01 0013 014 150 15/016 M017 018 819 019 ... ... 0020 0021 ....
0022 023 024 025 00.26 ... ... .... .... 0027
TEXT;

        $waypoints = (new WaypointExtractor)->extract($text)['data'];

        $this->assertSame(['FIX01', 'FIX01'], array_column($waypoints, 'identifier'));
        $this->assertSame(['005', '019'], array_column($waypoints, 'time'));
        $this->assertSame(['00.11', '00.26'], array_column($waypoints, 'total_time'));
        $this->assertSame(['0007', '0021'], array_column($waypoints, 'remaining_fuel'));
    }

    public function test_it_preserves_zero_remaining_fuel_and_leaves_missing_fuel_null(): void
    {
        $text = <<<'TEXT'
IDENT DIST MC FL WIND CMP TAS/MAC TIME ETA ATA TBO FRMG EFB
FRQ DTGO MH W/S OAT G/S T/TME REV REM ABO AFOB DSTN
N01 02.3 E004 05.6
ZERO 0001 001 CLB 01/002 P003 004 CLB 005 ... ... 0006 0000 ....
0008 009 CLB 010 00.11 ... ... .... .... 0012
N02 03.4 E005 06.7
EMPTY ---- --- --- --/--- ---- --- --- --- ... ... ---- ---- ....
------ ---- --- --- --- 00.12 ... ... .... .... 0011
TEXT;

        $waypoints = (new WaypointExtractor)->extract($text)['data'];

        $this->assertSame('0000', $waypoints[0]['remaining_fuel']);
        $this->assertNull($waypoints[1]['remaining_fuel']);
    }

    public function test_it_does_not_reuse_a_coordinate_for_a_coordinate_less_marker(): void
    {
        $text = <<<'TEXT'
IDENT DIST MC FL WIND CMP TAS/MAC TIME ETA ATA TBO FRMG EFB
FRQ DTGO MH W/S OAT G/S T/TME REV REM ABO AFOB DSTN
N52 07.7 E010 49.7
POVEL 0020 278 CLB 27/042 M041 491 CLB 003 ... ... 0104 1757 ....
3923 277 CLB 449 00.13 ... ... .... .... 1446
TOC 0021 259 CLB 28/038 M037 498 CLB 002 ... ... 0119 1741 ....
3902 260 CLB 461 00.15 ... ... .... .... 1431
TEXT;

        $waypoints = (new WaypointExtractor)->extract($text)['data'];

        $this->assertCount(1, $waypoints);
        $this->assertSame('POVEL', $waypoints[0]['identifier']);
    }

    public function test_it_handles_crlf_and_extra_horizontal_whitespace(): void
    {
        $text = str_replace("\n", "\r\n", $this->fixture('computed-flight-plan.txt'));
        $text = str_replace('DP550 0011', "DP550\t0011", $text);

        $waypoints = (new WaypointExtractor)->extract($text)['data'];

        $this->assertCount(10, $waypoints);
        $this->assertSame('DP550', $waypoints[1]['identifier']);
    }

    public function test_it_extracts_waypoints_from_a_flattened_pdf_text_layer(): void
    {
        $text = 'SUPPLIER AVFUELVENDOR AERO FUELSIDENT DIST MC FL WIND CMP TAS/MAC TIME ETA ATA TBO FRMG EFB'
            .'FRQ DTGO MH W/S OAT G/S T/TME REV REM ABO AFOB DSTN'
            .'ANC FIELD N61 10.4/W149 59.9'
            .'N60 28.9 W146 36.0'
            .'JOH 0108 098 CLB 05/015 M008 403 CLB 017 .. .. 0128 1480 .. '
            .'116.70 3433 096 LGT CLB 394 00.17 .. .. .. .. 1197'
            .'N59 55.5 W144 11.1'
            .'FIX01 0023 144 330 11/006 M005 460 838 004 .. .. 1308 0300 .. '
            .'0087 144 330 454 00.21 .. .. .. .. 0017'
            .' ----------------------- ALTERNATE ---------------------'
            .'IDENT DIST MC FL WIND CMP TAS/MAC TIME ETA ATA TBO FRMG EFB'
            .'FRQ DTGO MH W/S OAT G/S T/TME REV REM ABO AFOB DSTN'
            .'N26 21.8 W080 55.6'
            .'MRENO 0036 322 100 18/003 P002 295 452 007 .. .. 0053 0230 ..';

        $waypoints = (new WaypointExtractor)->extract($text)['data'];

        $this->assertSame([
            ['coordinate' => 'N60 28.9 W146 36.0', 'identifier' => 'JOH', 'time' => '017', 'total_time' => '00.17', 'remaining_fuel' => '1480'],
            ['coordinate' => 'N59 55.5 W144 11.1', 'identifier' => 'FIX01', 'time' => '004', 'total_time' => '00.21', 'remaining_fuel' => '0300'],
        ], $waypoints);
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../Fixtures/FlightPlan/waypoints/'.$name);
        $this->assertIsString($contents);

        return $contents;
    }
}
