<?php

namespace Tests\Unit;

use App\Services\FlightPlan\Extractor\DispatcherNotesExtractor;
use App\Services\FlightPlan\Extractor\FlightPlanTextExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DispatcherNotesExtractorTest extends TestCase
{
    #[Test]
    public function it_extracts_boxed_dispatcher_notes_in_source_order(): void
    {
        $text = <<<'TEXT'
        KALITTA AIR RELEASE TIME 0009 / FLIGHT FOLLOWER J HAFT.
        ******************************************************************
        *** FUEL BURN INCLUDES 0.5% PENALTY FOR PERISHABLES ***
        ******************************************************************
        *** APPROVED SLOT TIMES: DEP 0215Z (+/- 30 MIN ) ARR 0445Z (+/- 30 MIN )
        ******************************************************************
        * ETOPS PRE-DEPARTURE BRIEF COMPLETE * * * * PDSC START..Z PDSC END..Z *
        *********************************************************************
        BASED ON FORECAST WINDS: PLANNED TO DEPT RUNWAY: 34L TETRA8 ENPAR PLANNED TO ARRV RUNWAY: 34R GUKDO GUKD2E.
        *****************************************************************
        * DUE TO THE B777-300ERSF CONVERSION, THE FOLLOWING STATUS * * MESSAGES MAY BE DISPLAYED: DETECTOR IFES SMOKE, IFES COOLING * * FAN, NITROGEN GEN SYS. THE FOLLOWING EICAS MESSAGE MAY BE * * DISPLAYED PASS OXYGEN LOW. ENGINEERING ORDER (EO) 25-01 * * COVERS THESE DIFFERENCES AND MAINTENANCE WRITE UP NOT * * REQUIRED. *
        *****************************************************************
        * TIRE PRESSURE INDICATION SYSTEM DEACTIVATED BY EO 3249-25-01. * * THE FOLLOWING STATUS MESSAGES MAY BE DISPLAYED AND A * * MAINTENACE WRITE UP IS NOT REQUIRED TIRE PRESS, TIRE PRESS SYS *
        ******************************************************************
        * THIS AIRCRAFT IS PART OF THE OF THE EUROCONTROL DATALINK * * LOGON LIST - CPDLC LOGON IS MANDATORY IN EUROCONTOL AIRSPACE *
        *****************************************************************
        * AIRCRAFT REPETITIVE MAINTENANCE ITEMS: N/A *
        ******************************************************************
        * REFER TO FSA PRIOR TO DEPARTURE: 24-03 *
        ******************************************************************
        MEL/CDL M 33-21-01-02
        TEXT;

        $result = (new DispatcherNotesExtractor)->extract($text);

        $this->assertSame([
            'FUEL BURN INCLUDES 0.5% PENALTY FOR PERISHABLES',
            'DUE TO THE B777-300ERSF CONVERSION, THE FOLLOWING STATUS MESSAGES MAY BE DISPLAYED: DETECTOR IFES SMOKE, IFES COOLING FAN, NITROGEN GEN SYS THE FOLLOWING EICAS MESSAGE MAY BE DISPLAYED PASS OXYGEN LOW ENGINEERING ORDER (EO) 25-01 COVERS THESE DIFFERENCES AND MAINTENANCE WRITE UP NOT REQUIRED.',
            'TIRE PRESSURE INDICATION SYSTEM DEACTIVATED BY EO 3249-25-01. THE FOLLOWING STATUS MESSAGES MAY BE DISPLAYED AND A MAINTENACE WRITE UP IS NOT REQUIRED TIRE PRESS, TIRE PRESS SYS',
            'THIS AIRCRAFT IS PART OF THE OF THE EUROCONTROL DATALINK LOGON LIST - CPDLC LOGON IS MANDATORY IN EUROCONTOL AIRSPACE',
            'AIRCRAFT REPETITIVE MAINTENANCE ITEMS: N/A',
            'REFER TO FSA PRIOR TO DEPARTURE:  24-03',
        ], $result['data']);
        $this->assertStringContainsString('FUEL BURN INCLUDES', $result['source_fragments']['dispatcher_notes']);
    }

    #[Test]
    public function it_extracts_non_etops_forecast_notes_and_unavailable_approved_slots(): void
    {
        $text = <<<'TEXT'
        RELEASE TIME 0621 / FLIGHT FOLLOWER J QUEEN
        *** 12Z SIGWX FCST MOD TURB TO FL400 TIMMR-DITZL
        *** ADDNL 1.5 FOR PANC DEPARTURE VECTORS
        *** APPROVED SLOT TIMES: N/A
        *** BASED ON FORECAST WINDS: PLANNED TO DEPT RUNWAY: 15 PLANNED TO ARRV RUNWAY: 09 ACORI FROGZ5.
        ******************************************************************
        * REFER TO FSA PRIOR TO DEPARTURE: 24-03 *
        ******************************************************************MEL/CDLNONE
        TEXT;

        $result = (new DispatcherNotesExtractor)->extract($text);

        $this->assertSame([
            '12Z SIGWX FCST MOD TURB TO FL400 TIMMR-DITZL',
            'ADDNL 1.5 FOR PANC DEPARTURE VECTORS',
            'APPROVED SLOT TIMES: N/A',
            "BASED ON FORECAST WINDS:\nPLANNED TO DEPT RUNWAY: 15\nPLANNED TO ARRV RUNWAY: 09    ACORI FROGZ5",
            'REFER TO FSA PRIOR TO DEPARTURE:  24-03',
        ], $result['data']);
    }

    #[Test]
    public function it_extracts_dash_delimited_notes_and_multiline_note_groups(): void
    {
        $text = <<<'TEXT'
        RELEASE TIME 0335 / FLIGHT FOLLOWER A THEISEN.
        -- SLOT TIMES: - N/A
        -- FUEL BURN INCLUDES 0.5% PENALTY FOR PRSHBLS
        -- PLANNED FL350 THROUGH NAT HLA ON A RANDOM ROUTE
        -- ENROUTE SIGWX NOTATIONS: - 12Z: MOD TURB 54N040W-55N020W (XXX-400)
        ******************************************************************
        * ETOPS PRE-DEPARTURE BRIEF COMPLETE *
        *********************************************************************
        BASED ON FORECAST WINDS: PLANNED TO DEPT RUNWAY: 27 PLANNED TO ARRV RUNWAY: 08L.
        ***************************************************************
        * AIRCRAFT REPETITIVE MAINTENANCE ITEMS: * * STATIC FEEDBACK IN FLIGHT INTERPHONE *
        ***************************************************************
        * REFER TO FSA PRIOR TO DEPARTURE: 24-03 *
        ******************************************************************MEL/CDL
        TEXT;

        $result = (new DispatcherNotesExtractor)->extract($text);

        $this->assertSame([
            "SLOT TIMES:\n- N/A",
            'FUEL BURN INCLUDES 0.5% PENALTY FOR PRSHBLS',
            'PLANNED FL350 THROUGH NAT HLA ON A RANDOM ROUTE',
            "ENROUTE SIGWX NOTATIONS:\n- 12Z: MOD TURB 54N040W-55N020W (XXX-400)",
            "AIRCRAFT REPETITIVE MAINTENANCE ITEMS:\nSTATIC FEEDBACK IN FLIGHT INTERPHONE",
            'REFER TO FSA PRIOR TO DEPARTURE:  24-03',
        ], $result['data']);
    }

    #[Test]
    #[DataProvider('governmentForecastNoteLayoutProvider')]
    public function it_extracts_government_forecast_and_additional_fuel_notes(string $separator, string $ending): void
    {
        $text = implode($separator, [
            'RELEASE TIME 0600 / FLIGHT FOLLOWER TEST',
            '-- GOVT SIGWX PROG FORECASTS AREAS OF:',
            '   - OCNL CB WYPTS MAGOG-VHHH  SFC-FL440',
            '-- ADDITIONAL FUEL ADDED:',
            ' - 30 MINS HOLD FUEL DUE TO WX, T-STORMS AT',
            '      ETA TO DESTINATION (VHHH)',
        ]).$ending;

        $result = (new DispatcherNotesExtractor)->extract($text);

        $expected = [
            "GOVT SIGWX PROG FORECASTS AREAS OF:\n- OCNL CB WYPTS MAGOG-VHHH SFC-FL440",
            "ADDITIONAL FUEL ADDED:\n- 30 MINS HOLD FUEL DUE TO WX, T-STORMS AT ETA TO DESTINATION (VHHH)",
        ];

        $this->assertSame($expected, $result['data']);
        $this->assertSame(implode("\n\n", $expected), $result['source_fragments']['dispatcher_notes']);
    }

    /** @return array<string, array{string, string}> */
    public static function governmentForecastNoteLayoutProvider(): array
    {
        return [
            'multiline boxed boundary' => ["\n", "\n******************************************************************\nMEL/CDL NONE"],
            'flattened next-note boundary' => [' ', ' -- UNRELATED DISPATCH NOTE MEL/CDL NONE'],
            'CRLF MEL boundary' => ["\r\n", "\r\nMEL/CDL NONE\r\n-- ADDITIONAL FUEL ADDED: - OUTSIDE HEADER"],
            'end of text' => ["\n", ''],
        ];
    }

    #[Test]
    public function it_ignores_text_outside_the_dispatch_header_and_returns_an_empty_result_without_a_header(): void
    {
        $extractor = new DispatcherNotesExtractor;

        $this->assertSame(
            ['data' => [], 'source_fragments' => []],
            $extractor->extract('FUEL BURN INCLUDES 0.5% PENALTY FOR PERISHABLES'),
        );

        $result = $extractor->extract(
            'RELEASE TIME 0100 / FLIGHT FOLLOWER TEST MEL/CDL NONE '
            .'AIRCRAFT REPETITIVE MAINTENANCE ITEMS: LATER COMPANY NOTICE',
        );

        $this->assertSame([], $result['data']);
    }

    /** @param list<string> $expected */
    #[Test]
    #[DataProvider('privateReleaseProvider')]
    public function private_release_extracts_the_expected_dispatcher_notes(string $filename, array $expected): void
    {
        $path = storage_path('app/private/flight_releases/'.$filename);

        if (! is_file($path)) {
            $this->markTestSkipped("Private release {$filename} is not available.");
        }

        $text = app(FlightPlanTextExtractor::class)->extract($path);

        $this->assertSame($expected, (new DispatcherNotesExtractor)->extract($text)['data']);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function privateReleaseProvider(): array
    {
        return [
            'RJGG release' => [
                'CKS024201RJGG.pdf',
                [
                    "GOVT SIGWX PROG FORECASTS AREAS OF:\n- OCNL CB WYPTS MAGOG-VHHH SFC-FL440",
                    "ADDITIONAL FUEL ADDED:\n- 30 MINS HOLD FUEL DUE TO WX, T-STORMS AT ETA TO DESTINATION (VHHH)",
                    "BASED ON FORECAST WINDS:\nPLANNED TO DEPT RUNWAY: 36 ISE3 ESPAN\nPLANNED TO ARRV RUNWAY: 25C    ABBEY ABBE3B",
                    'AIRCRAFT REPETITIVE MAINTENANCE ITEMS: N/A',
                    'REFER TO FSA PRIOR TO DEPARTURE:  24-03',
                ],
            ],
            'RJAA release' => [
                'CKS021617RJAA.pdf',
                [
                    'FUEL BURN INCLUDES 0.5% PENALTY FOR PERISHABLES',
                    'DUE TO THE B777-300ERSF CONVERSION, THE FOLLOWING STATUS MESSAGES MAY BE DISPLAYED: DETECTOR IFES SMOKE, IFES COOLING FAN, NITROGEN GEN SYS THE FOLLOWING EICAS MESSAGE MAY BE DISPLAYED PASS OXYGEN LOW ENGINEERING ORDER (EO) 25-01 COVERS THESE DIFFERENCES AND MAINTENANCE WRITE UP NOT REQUIRED.',
                    'TIRE PRESSURE INDICATION SYSTEM DEACTIVATED BY EO 3249-25-01. THE FOLLOWING STATUS MESSAGES MAY BE DISPLAYED AND A MAINTENACE WRITE UP IS NOT REQUIRED TIRE PRESS, TIRE PRESS SYS',
                    'THIS AIRCRAFT IS PART OF THE OF THE EUROCONTROL DATALINK LOGON LIST - CPDLC LOGON IS MANDATORY IN EUROCONTOL AIRSPACE',
                    'AIRCRAFT REPETITIVE MAINTENANCE ITEMS: N/A',
                    'REFER TO FSA PRIOR TO DEPARTURE:  24-03',
                ],
            ],
            'PANC release' => [
                'CKS024125PANC.pdf',
                [
                    '12Z SIGWX FCST MOD TURB TO FL400 TIMMR-DITZL',
                    'ADDNL 1.5 FOR PANC DEPARTURE VECTORS',
                    'APPROVED SLOT TIMES: N/A',
                    "BASED ON FORECAST WINDS:\nPLANNED TO DEPT RUNWAY: 15\nPLANNED TO ARRV RUNWAY: 09    ACORI FROGZ5",
                    'REFER TO FSA PRIOR TO DEPARTURE:  24-03',
                ],
            ],
            'KCVG release' => [
                'CKS024726KCVG.pdf',
                [
                    "SLOT TIMES:\n- N/A",
                    'FUEL BURN INCLUDES 0.5% PENALTY FOR PRSHBLS',
                    'PLANNED FL350 THROUGH NAT HLA ON A RANDOM ROUTE',
                    "ENROUTE SIGWX NOTATIONS:\n- 12Z: MOD TURB 54N040W-55N020W (XXX-400)",
                    "AIRCRAFT REPETITIVE MAINTENANCE ITEMS:\nSTATIC FEEDBACK IN FLIGHT INTERPHONE",
                    'REFER TO FSA PRIOR TO DEPARTURE:  24-03',
                ],
            ],
        ];
    }
}
