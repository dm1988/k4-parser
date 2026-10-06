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
            ['coordinate' => 'N51 25.9 E012 16.1', 'identifier' => '51259N', 'display_label' => '51259N', 'kind' => 'fix', 'time' => '001', 'total_time' => '00.01', 'remaining_fuel' => '1853', 'tbo' => '0007'],
            ['coordinate' => 'N51 36.5 E012 11.5', 'identifier' => 'DP550', 'display_label' => 'DP550', 'kind' => 'fix', 'time' => '002', 'total_time' => '00.03', 'remaining_fuel' => '1825', 'tbo' => '0035'],
            ['coordinate' => 'N51 42.5 E012 05.3', 'identifier' => 'PENEM', 'display_label' => 'PENEM', 'kind' => 'fix', 'time' => '002', 'total_time' => '00.05', 'remaining_fuel' => '1813', 'tbo' => '0047'],
            ['coordinate' => 'N51 51.0 E011 50.3', 'identifier' => 'ODLUN', 'display_label' => 'ODLUN', 'kind' => 'fix', 'time' => '002', 'total_time' => '00.07', 'remaining_fuel' => '1797', 'tbo' => '0064'],
            ['coordinate' => 'N51 52.9 E011 45.9', 'identifier' => '-EDWW', 'display_label' => 'EDWW (FIR)', 'kind' => 'fir', 'time' => null, 'total_time' => '00.07', 'remaining_fuel' => '1793', 'tbo' => null],
            ['coordinate' => 'N52 03.5 E011 21.0', 'identifier' => 'EMBOX', 'display_label' => 'EMBOX', 'kind' => 'fix', 'time' => '003', 'total_time' => '00.10', 'remaining_fuel' => '1774', 'tbo' => '0086'],
            ['coordinate' => 'N52 05.9 E011 03.5', 'identifier' => '-EDVV', 'display_label' => 'EDVV (FIR)', 'kind' => 'fir', 'time' => null, 'total_time' => '00.12', 'remaining_fuel' => '1765', 'tbo' => null],
            ['coordinate' => 'N52 07.7 E010 49.7', 'identifier' => 'POVEL', 'display_label' => 'POVEL', 'kind' => 'fix', 'time' => '003', 'total_time' => '00.13', 'remaining_fuel' => '1757', 'tbo' => '0104'],
            ['coordinate' => null, 'identifier' => 'TOC', 'display_label' => 'TOC', 'kind' => 'toc', 'time' => '002', 'total_time' => '00.15', 'remaining_fuel' => '1741', 'tbo' => '0119'],
            ['coordinate' => 'N51 51.4 E007 42.5', 'identifier' => 'HMM', 'display_label' => 'HMM', 'kind' => 'fix', 'time' => '013', 'total_time' => '00.28', 'remaining_fuel' => '1702', 'tbo' => '0158'],
            ['coordinate' => 'N51 50.4 E006 25.9', 'identifier' => '-EHAA', 'display_label' => 'EHAA (FIR)', 'kind' => 'fir', 'time' => null, 'total_time' => '00.34', 'remaining_fuel' => '1683', 'tbo' => null],
        ], $result['data']);

        $this->assertArrayHasKey('computed_flight_plan_waypoints', $result['source_fragments']);
        $this->assertStringContainsString('TOC 002 00.15 1741 0119', $result['source_fragments']['computed_flight_plan_waypoints']);
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
        $this->assertSame(['0006', '0020'], array_column($waypoints, 'tbo'));
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
        $this->assertSame('0006', $waypoints[0]['tbo']);
        $this->assertNull($waypoints[1]['tbo']);
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

        $this->assertCount(2, $waypoints);
        $this->assertSame('POVEL', $waypoints[0]['identifier']);
        $this->assertSame('00.13', $waypoints[0]['total_time']);
        $this->assertSame('TOC', $waypoints[1]['identifier']);
        $this->assertNull($waypoints[1]['coordinate']);
        $this->assertSame('00.15', $waypoints[1]['total_time']);
        $this->assertSame('1741', $waypoints[1]['remaining_fuel']);
    }

    public function test_it_handles_crlf_and_extra_horizontal_whitespace(): void
    {
        $text = str_replace("\n", "\r\n", $this->fixture('computed-flight-plan.txt'));
        $text = str_replace('DP550 0011', "DP550\t0011", $text);

        $waypoints = (new WaypointExtractor)->extract($text)['data'];

        $this->assertCount(11, $waypoints);
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
            ['coordinate' => 'N60 28.9 W146 36.0', 'identifier' => 'JOH', 'display_label' => 'JOH', 'kind' => 'fix', 'time' => '017', 'total_time' => '00.17', 'remaining_fuel' => '1480', 'tbo' => '0128'],
            ['coordinate' => 'N59 55.5 W144 11.1', 'identifier' => 'FIX01', 'display_label' => 'FIX01', 'kind' => 'fix', 'time' => '004', 'total_time' => '00.21', 'remaining_fuel' => '0300', 'tbo' => '1308'],
        ], $waypoints);
    }

    public function test_it_restores_source_identifiers_from_flattened_coordinate_boundaries(): void
    {
        $waypoints = (new WaypointExtractor)->extract($this->fixture('flattened-boundaries.txt'))['data'];

        $this->assertSame(
            ['39028N', '50N095', '-CZEG', '61N130', '-PAZA', 'GAHAM', 'NODLE', '-ETP1'],
            array_column($waypoints, 'identifier'),
        );
        $this->assertSame(
            ['N39 02.8 W084 41.7', 'N50 00.0 W095 00.0', 'N56 54.1 W105 00.3',
                'N61 00.0 W130 00.0', 'N62 15.0 W141 00.0', 'N62 15.0 W141 00.0',
                'N61 17.0 W152 00.0', 'N60 00.0 W160 00.0'],
            array_column($waypoints, 'coordinate'),
        );
        $this->assertSame(
            ['39028N', 'N50W095', 'CZEG (FIR)', 'N61W130', 'PAZA (FIR)', 'GAHAM', 'NODLE', '-ETP1'],
            array_column($waypoints, 'display_label'),
        );
        $this->assertSame(['fix', 'fix', 'fir', 'fix', 'fir', 'fix', 'fix', 'fix'], array_column($waypoints, 'kind'));
        $this->assertSame('028', $waypoints[1]['time']);
        $this->assertSame('2271', $waypoints[1]['remaining_fuel']);
        $this->assertSame('0452', $waypoints[1]['tbo']);
        $this->assertSame('06.00', $waypoints[6]['total_time']);
    }

    public function test_it_keeps_the_same_waypoints_when_coordinate_and_identifier_are_separated(): void
    {
        $waypoints = (new WaypointExtractor)->extract($this->fixture('separated-boundaries.txt'))['data'];

        $this->assertSame(
            ['39028N', '50N095', '-CZEG', '61N130', '-PAZA', 'GAHAM', 'NODLE', '-ETP1'],
            array_column($waypoints, 'identifier'),
        );
        $this->assertSame(['N50W095', 'CZEG (FIR)', 'N61W130', 'PAZA (FIR)'], [
            $waypoints[1]['display_label'],
            $waypoints[2]['display_label'],
            $waypoints[3]['display_label'],
            $waypoints[4]['display_label'],
        ]);
    }

    public function test_it_does_not_invent_a_whole_degree_label_or_split_high_precision_minutes(): void
    {
        $text = <<<'TEXT'
IDENT DIST MC FL WIND CMP TAS/MAC TIME ETA ATA TBO FRMG EFB
FRQ DTGO MH W/S OAT G/S T/TME REV REM ABO AFOB DSTN
N50 00.1 W095 00.0
50N095 0001 001 100 01/001 P001 001 001 001 ... ... 0001 0001 ....
0001 001 100 001 00.01 ... ... .... .... 0001
N50 00.0 W095 00.050
NODLE 0001 001 100 01/001 P001 001 001 001 ... ... 0001 0001 ....
0001 001 100 001 00.02 ... ... .... .... 0001
S50 00.0 E095 00.0
50S095 0001 001 100 01/001 P001 001 001 001 ... ... 0001 0001 ....
0001 001 100 001 00.03 ... ... .... .... 0001
TEXT;

        $waypoints = (new WaypointExtractor)->extract($text)['data'];

        $this->assertSame('50N095', $waypoints[0]['display_label']);
        $this->assertSame('N50 00.0 W095 00.050', $waypoints[1]['coordinate']);
        $this->assertSame('NODLE', $waypoints[1]['display_label']);
        $this->assertSame('S50E095', $waypoints[2]['display_label']);
    }

    public function test_it_preserves_phase_marker_order_with_optional_coordinates_and_flattened_text(): void
    {
        $text = <<<'TEXT'
IDENT DIST MC FL WIND CMP TAS/MAC TIME ETA ATA TBO FRMG EFB
FRQ DTGO MH W/S OAT G/S T/TME REV REM ABO AFOB DSTN
LEJ FIELD N51 25.4/E012 14.2
TOC 0021 259 CLB 28/038 M037 498 CLB 002 ... ... 0119 1741 ....
3902 260 CLB 461 00.15 ... ... .... .... 1431
N52 07.7 E010 49.7
FIX01 0020 278 320 27/042 M041 491 837 003 ... ... 0120 1740 ....
3923 277 320 449 00.18 ... ... .... .... 1446
N51 51.4 E007 42.5
KALITTA BRIEF PAGE 5 OF 8
TOC 0021 259 CLB 28/038 M037 498 CLB 002 ... ... 0130 1730 ....
3902 260 CLB 461 00.20 ... ... .... .... 1431
TOD 0021 259 DSC 28/038 M037 498 DSC 002 ... ... 0140 1720 ....
3902 260 DSC 461 00.22 ... ... .... .... 1431
N51 50.4 E006 25.9
TOD 0021 259 DSC 28/038 M037 498 DSC 002 ... ... 0150 1710 ....
3902 260 DSC 461 00.24 ... ... .... .... 1431
----------------------- ALTERNATE ---------------------
TOC 0021 259 CLB 28/038 M037 498 CLB 002 ... ... 0160 1700 ....
3902 260 CLB 461 00.26 ... ... .... .... 1431
TEXT;

        foreach ([$text, preg_replace('/\s+/', ' ', $text), str_replace("\n", "\r\n", $text)] as $source) {
            $result = (new WaypointExtractor)->extract($source);
            $waypoints = $result['data'];

            $this->assertSame(['TOC', 'FIX01', 'TOC', 'TOD', 'TOD'], array_column($waypoints, 'identifier'));
            $this->assertSame(['toc', 'fix', 'toc', 'tod', 'tod'], array_column($waypoints, 'kind'));
            $this->assertSame([null, 'N52 07.7 E010 49.7', 'N51 51.4 E007 42.5', null, 'N51 50.4 E006 25.9'], array_column($waypoints, 'coordinate'));
            $this->assertSame(['00.15', '00.18', '00.20', '00.22', '00.24'], array_column($waypoints, 'total_time'));
            $this->assertSame(['1741', '1740', '1730', '1720', '1710'], array_column($waypoints, 'remaining_fuel'));
            $this->assertSame(['0119', '0120', '0130', '0140', '0150'], array_column($waypoints, 'tbo'));
            $this->assertStringContainsString('TOD 002 00.22 1720 0140', $result['source_fragments']['computed_flight_plan_waypoints']);
        }
    }

    public function test_it_keeps_missing_phase_fields_null_and_does_not_borrow_adjacent_values(): void
    {
        $text = <<<'TEXT'
IDENT DIST MC FL WIND CMP TAS/MAC TIME ETA ATA TBO FRMG EFB
FRQ DTGO MH W/S OAT G/S T/TME REV REM ABO AFOB DSTN
N52 07.7 E010 49.7
FIX01 0020 278 CLB 27/042 M041 491 CLB --- ... ... ---- ---- ....
TOC ---- --- --- --/--- ---- --- --- --- ... ... ---- ---- ....
TOD 0000 259 DSC 28/038 M037 498 DSC 000 ... ... 0000 0000 ....
3902 260 DSC 461 00.00 ... ... .... .... 1431
TEXT;

        $waypoints = (new WaypointExtractor)->extract($text)['data'];

        $this->assertSame(['FIX01', 'TOC', 'TOD'], array_column($waypoints, 'identifier'));
        $this->assertSame([null, null, '00.00'], array_column($waypoints, 'total_time'));
        $this->assertSame([null, null, '0000'], array_column($waypoints, 'remaining_fuel'));
        $this->assertSame([null, null, '0000'], array_column($waypoints, 'tbo'));
        $this->assertSame([null, null, '000'], array_column($waypoints, 'time'));
    }

    public function test_it_extracts_phase_markers_from_cks024201rjgg_text(): void
    {
        $source = $this->fixture('cks024201rjgg-computed.txt');
        $this->assertStringContainsString('0460TOC', $source);
        $this->assertStringContainsString('<-TOD', $source);
        $result = (new WaypointExtractor)->extract($source);
        $phases = array_values(array_filter($result['data'], static fn (array $waypoint): bool => in_array($waypoint['identifier'], ['TOC', 'TOD'], true)));

        $this->assertSame([
            ['coordinate' => null, 'identifier' => 'TOC', 'display_label' => 'TOC', 'kind' => 'toc', 'time' => '006', 'total_time' => '00.15', 'remaining_fuel' => '0801', 'tbo' => '0103'],
            ['coordinate' => null, 'identifier' => 'TOD', 'display_label' => 'TOD', 'kind' => 'tod', 'time' => '007', 'total_time' => '02.54', 'remaining_fuel' => '0400', 'tbo' => '0505'],
        ], $phases);
        $this->assertCount(49, $result['data']);
        $identifiers = array_column($result['data'], 'identifier');
        $this->assertSame(['ESPAN', 'TOC', 'KEC'], array_slice($identifiers, 6, 3));
        $this->assertSame(['-VHHK', 'TOD', 'MAGOG'], array_slice($identifiers, 39, 3));
        $this->assertSame('02.47', $result['data'][39]['total_time']);
        $this->assertNull($result['data'][39]['tbo']);
        $this->assertStringContainsString('TOC 006 00.15 0801 0103', $result['source_fragments']['computed_flight_plan_waypoints']);
        $this->assertStringContainsString('TOD 007 02.54 0400 0505', $result['source_fragments']['computed_flight_plan_waypoints']);
    }

    public function test_it_does_not_split_fix_identifiers_ending_with_phase_marker_letters(): void
    {
        $text = <<<'TEXT'
IDENT DIST MC FL WIND CMP TAS/MAC TIME ETA ATA TBO FRMG EFB
FRQ DTGO MH W/S OAT G/S T/TME REV REM ABO AFOB DSTN
N01 02.3 E004 05.6
1234TOC 0001 001 CLB 01/002 P003 004 CLB 005 ... ... 0006 0007 ....
0008 009 CLB 010 00.11 ... ... .... .... 0012
N02 03.4 E005 06.7
1234TOD 0013 014 150 15/016 M017 018 819 019 ... ... 0020 0021 ....
0022 023 024 025 00.26 ... ... .... .... 0027
TEXT;

        $waypoints = (new WaypointExtractor)->extract($text)['data'];

        $this->assertSame(['1234TOC', '1234TOD'], array_column($waypoints, 'identifier'));
        $this->assertSame(['fix', 'fix'], array_column($waypoints, 'kind'));
        $this->assertSame(['00.11', '00.26'], array_column($waypoints, 'total_time'));
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../Fixtures/FlightPlan/waypoints/'.$name);
        $this->assertIsString($contents);

        return $contents;
    }
}
