<?php

namespace Tests\Unit;

use App\Exceptions\FlightRouteNotFoundException;
use App\Services\FlightRouteExtractor;
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
}
