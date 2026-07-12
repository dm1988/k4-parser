<?php

namespace Tests\Unit;

use App\Exceptions\FlightRouteNotFoundException;
use App\Services\FlightRouteExtractor;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;

class FlightRouteExtractorTest extends TestCase
{
    public function test_extract_route_from_text_returns_the_route_block(): void
    {
        $extractor = new FlightRouteExtractor(new Parser);

        $text = <<<'TEXT'
(FPL-CKS272-IS
-B77L/H-SDE2E3FGHIJ1J4J5M1P2RWXYZ/LB1D1G1
-SBKP1000
-N0487F360 OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105
 UMKAL UMKAL6A
-SCEL0322 SAME
-PBN/A1L1B1C1D1O1S2 NAV/Z1 RNP2 COM/SAT PHONE  52472150 DAT/1FANSE
 SUR/260B RSP180 CANMANDATE DOF/260627 REG/N773CK)
TEXT;

        $route = $extractor->extractRouteFromText($text);

        $this->assertSame("OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105\nUMKAL UMKAL6A", $route);
    }

    public function test_extract_route_from_flattened_pdf_text_returns_the_route_block(): void
    {
        $extractor = new FlightRouteExtractor(new Parser);

        $text = <<<'TEXT'
(FPL-CKS272-IS-B77L/H-SDE2E3FGHIJ1J4J5M1P2RWXYZ/LB1D1G1-SBKP1000-N0487F360 OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105 UMKAL UMKAL6A-SCEL0322 SAME-PBN/A1L1B1C1D1O1S2 NAV/Z1)
TEXT;

        $route = $extractor->extractRouteFromText($text);

        $this->assertSame('OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105 UMKAL UMKAL6A', $route);
    }

    public function test_extract_flight_plan_data_from_text_returns_departure_destination_alternate_altitude_duration_and_route(): void
    {
        $extractor = new FlightRouteExtractor(new Parser);

        $text = <<<'TEXT'
(FPL-CKS241-IS
-B77L/H-SDE2E3FGHIJ1J4J5M1P2RWXYZ/LB1D1G1
-PANC1040
-N0489F330 DCT JOH DCT YAK J541 SSR DCT 5726N13228W DCT YXS DCT
 DESNU DCT HASOS DCT TIMMR DCT FSD Q19 DSM/N0486F350 J45 IRK DCT
 FAM J151 GETME DCT VLKNN Q139 MGMRY DCT ACORI FROGZ5
-KMIA0712 KRSW
-PBN/A1L1B1C1D1O1S2 NAV/Z1)
TEXT;

        $flightPlan = $extractor->extractFlightPlanDataFromText($text);

        $this->assertSame([
            'departure' => 'PANC',
            'destination' => 'KMIA',
            'alternate' => 'KRSW',
            'initial_altitude' => 'FL 330',
            'duration' => '07h12m',
            'route' => "DCT JOH DCT YAK J541 SSR DCT 5726N13228W DCT YXS DCT\nDESNU DCT HASOS DCT TIMMR DCT FSD Q19 DSM/N0486F350 J45 IRK DCT\nFAM J151 GETME DCT VLKNN Q139 MGMRY DCT ACORI FROGZ5",
        ], $flightPlan);
    }

    public function test_extract_route_uses_the_pdf_parser_output(): void
    {
        $document = $this->createMock(Document::class);
        $document->expects($this->once())
            ->method('getText')
            ->willReturn("(FPL-CKS272-IS\n-N0487F360 OSUDO4A ASETA\n-SCEL0322)");

        $parser = $this->createMock(Parser::class);
        $parser->expects($this->once())
            ->method('parseFile')
            ->with('/tmp/flight-release.pdf')
            ->willReturn($document);

        $extractor = new FlightRouteExtractor($parser);

        $this->assertSame('OSUDO4A ASETA', $extractor->extractRoute('/tmp/flight-release.pdf'));
    }

    public function test_extract_flight_plan_data_uses_the_pdf_parser_output(): void
    {
        $document = $this->createMock(Document::class);
        $document->expects($this->once())
            ->method('getText')
            ->willReturn("(FPL-CKS272-IS\n-B77L/H-SDE2E3FGHIJ1J4J5M1P2RWXYZ/LB1D1G1\n-SBKP1000\n-N0487F360 OSUDO4A ASETA\n-SCEL0322 SAME)");

        $parser = $this->createMock(Parser::class);
        $parser->expects($this->once())
            ->method('parseFile')
            ->with('/tmp/flight-release.pdf')
            ->willReturn($document);

        $extractor = new FlightRouteExtractor($parser);

        $this->assertSame([
            'departure' => 'SBKP',
            'destination' => 'SCEL',
            'alternate' => 'SAME',
            'initial_altitude' => 'FL 360',
            'duration' => '03h22m',
            'route' => 'OSUDO4A ASETA',
        ], $extractor->extractFlightPlanData('/tmp/flight-release.pdf'));
    }

    public function test_extractors_reuse_cached_pdf_text_for_the_same_file_hash(): void
    {
        $document = $this->createMock(Document::class);
        $document->expects($this->once())
            ->method('getText')
            ->willReturn("(FPL-CKS272-IS\n-B77L/H-SDE2E3FGHIJ1J4J5M1P2RWXYZ/LB1D1G1\n-SBKP1000\n-N0487F360 OSUDO4A ASETA\n-SCEL0322 SAME)");

        $parser = $this->createMock(Parser::class);
        $parser->expects($this->once())
            ->method('parseFile')
            ->with(__FILE__)
            ->willReturn($document);

        $extractor = new FlightRouteExtractor($parser, new CacheRepository(new ArrayStore));

        $this->assertSame('OSUDO4A ASETA', $extractor->extractRoute(__FILE__));
        $this->assertSame([
            'departure' => 'SBKP',
            'destination' => 'SCEL',
            'alternate' => 'SAME',
            'initial_altitude' => 'FL 360',
            'duration' => '03h22m',
            'route' => 'OSUDO4A ASETA',
        ], $extractor->extractFlightPlanData(__FILE__));
    }

    public function test_extract_flight_plan_data_from_text_sets_alternate_to_null_when_not_listed(): void
    {
        $extractor = new FlightRouteExtractor(new Parser);

        $text = <<<'TEXT'
(FPL-CKS272-IS
-B77L/H-SDE2E3FGHIJ1J4J5M1P2RWXYZ/LB1D1G1
-SBKP1000
-N0487F360 OSUDO4A ASETA
-SCEL0322
-PBN/A1L1B1C1D1O1S2 NAV/Z1)
TEXT;

        $flightPlan = $extractor->extractFlightPlanDataFromText($text);

        $this->assertNull($flightPlan['alternate']);
        $this->assertSame('SCEL', $flightPlan['destination']);
        $this->assertSame('03h22m', $flightPlan['duration']);
    }

    public function test_extract_route_from_text_throws_when_no_route_block_is_found(): void
    {
        $extractor = new FlightRouteExtractor(new Parser);

        $this->expectException(FlightRouteNotFoundException::class);
        $this->expectExceptionMessage('No ICAO flight plan block was found in the uploaded PDF.');

        $extractor->extractRouteFromText('No flight plan route block is present.');
    }

    public function test_extract_route_from_text_throws_detailed_error_when_route_segment_is_missing(): void
    {
        $extractor = new FlightRouteExtractor(new Parser);

        $text = <<<'TEXT'
(FPL-CKS272-IS
-B77L/H-SDE2E3FGHIJ1J4J5M1P2RWXYZ/LB1D1G1
-SBKP1000
-SCEL0322 SAME)
TEXT;

        $this->expectException(FlightRouteNotFoundException::class);
        $this->expectExceptionMessage(
            'A flight plan block was found, but the route segment could not be identified between the speed/level and destination lines.'
        );

        $extractor->extractRouteFromText($text);
    }

    public function test_extract_route_wraps_pdf_parser_failures_with_a_detailed_message(): void
    {
        $parser = $this->createMock(Parser::class);
        $parser->expects($this->once())
            ->method('parseFile')
            ->willThrowException(new \RuntimeException('Object list not found. Possible secured file.'));

        $extractor = new FlightRouteExtractor($parser);

        $this->expectException(FlightRouteNotFoundException::class);
        $this->expectExceptionMessage(
            'The uploaded PDF could not be read. Object list not found. Possible secured file.'
        );

        $extractor->extractRoute('/tmp/flight-release.pdf');
    }

    public function test_format_for_icao_display_wraps_route_on_token_boundaries(): void
    {
        $extractor = new FlightRouteExtractor(new Parser);

        $formattedRoute = $extractor->formatForIcaoDisplay(
            'OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105 UMKAL UMKAL6A'
        );

        $this->assertSame(
            "OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105\n UMKAL UMKAL6A",
            $formattedRoute
        );
    }

    public function test_format_for_icao_display_never_splits_fixes_coordinates_or_speed_level_tokens(): void
    {
        $extractor = new FlightRouteExtractor(new Parser);

        $formattedRoute = $extractor->formatForIcaoDisplay(
            'DCT 5230N05000W N0487F360 UM140 PRAWN DCT 52N030W KEMAX UL9'
        );

        $this->assertSame(
            "DCT 5230N05000W N0487F360 UM140 PRAWN DCT 52N030W KEMAX\n UL9",
            $formattedRoute
        );
        $this->assertStringNotContainsString("5230N0\n5000W", $formattedRoute);
        $this->assertStringNotContainsString("N0487\nF360", $formattedRoute);
        $this->assertStringNotContainsString("KE\nMAX", $formattedRoute);
    }
}
