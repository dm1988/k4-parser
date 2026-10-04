<?php

namespace Tests\Unit\Services\FlightPlan\Extractor;

use App\Services\FlightPlan\Extractor\TakeoffLandingReportSections;
use PHPUnit\Framework\TestCase;

class TakeoffLandingReportSectionsTest extends TestCase
{
    public function test_it_preserves_each_report_section_and_ignores_preamble(): void
    {
        $first = "TAKEOFF AND LANDING REPORT\nACARS INIT DATE 17\n";
        $second = "Takeoff  and\tLanding report\nACARS INIT DATE 18";

        $this->assertSame([$first, $second], (new TakeoffLandingReportSections)->extract('Preamble '.$first.$second));
    }

    public function test_it_requires_a_complete_heading(): void
    {
        $this->assertSame([], (new TakeoffLandingReportSections)->extract('TAKEOFF AND LANDING'));
        $this->assertSame([], (new TakeoffLandingReportSections)->extract(''));
    }
}
