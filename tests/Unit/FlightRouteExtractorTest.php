<?php

namespace Tests\Unit;

use App\DTOs\AirportData;
use App\Exceptions\FlightRouteNotFoundException;
use App\Services\Clients\AirportLookupClient;
use App\Services\FlightPlan\Extractor\FlightRouteExtractor;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Repository;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class FlightRouteExtractorTest extends TestCase
{
    public function test_extract_route_from_text_returns_the_route_block(): void
    {
        $extractor = $this->makeExtractor();

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
        $extractor = $this->makeExtractor();

        $text = <<<'TEXT'
(FPL-CKS272-IS-B77L/H-SDE2E3FGHIJ1J4J5M1P2RWXYZ/LB1D1G1-SBKP1000-N0487F360 OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105 UMKAL UMKAL6A-SCEL0322 SAME-PBN/A1L1B1C1D1O1S2 NAV/Z1)
TEXT;

        $route = $extractor->extractRouteFromText($text);

        $this->assertSame('OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105 UMKAL UMKAL6A', $route);
    }

    public function test_extract_flight_plan_data_from_text_returns_departure_destination_alternate_altitude_duration_and_route(): void
    {
        $departureAirport = new AirportData('PANC', 'ANC', 'Ted Stevens Anchorage International Airport', 'Anchorage', 'Alaska', 'United States');
        $destinationAirport = new AirportData('KMIA', 'MIA', 'Miami International Airport', 'Miami', 'Florida', 'United States');
        $alternateAirport = new AirportData('KRSW', 'RSW', 'Southwest Florida International Airport', 'Fort Myers', 'Florida', 'United States');

        $extractor = $this->makeExtractor(airportLookupClient: $this->fakeAirportLookupClient([
            'PANC' => $departureAirport,
            'KMIA' => $destinationAirport,
            'KRSW' => $alternateAirport,
        ]));

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
            'departure_airport' => $departureAirport,
            'destination_airport' => $destinationAirport,
            'alternate_airport' => $alternateAirport,
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

        $extractor = $this->makeExtractor($parser);

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

        $extractor = $this->makeExtractor($parser);

        $this->assertSame([
            'departure' => 'SBKP',
            'destination' => 'SCEL',
            'alternate' => 'SAME',
            'departure_airport' => null,
            'destination_airport' => null,
            'alternate_airport' => null,
            'initial_altitude' => 'FL 360',
            'duration' => '03h22m',
            'route' => 'OSUDO4A ASETA',
        ], $extractor->extractFlightPlanData('/tmp/flight-release.pdf'));
    }

    public function test_separate_extractor_instances_reuse_pdf_text_from_the_configured_cache_repository(): void
    {
        $pdfText = "(FPL-CKS272-IS\n-B77L/H-SDE2E3FGHIJ1J4J5M1P2RWXYZ/LB1D1G1\n-SBKP1000\n-N0487F360 OSUDO4A ASETA\n-SCEL0322 SAME)";
        $document = $this->createMock(Document::class);
        $document->expects($this->once())
            ->method('getText')
            ->willReturn($pdfText);

        $parser = $this->createMock(Parser::class);
        $parser->expects($this->once())
            ->method('parseFile')
            ->with(__FILE__)
            ->willReturn($document);

        $cache = app(Repository::class);
        $cacheKey = 'flight-route-extractor:pdf-text:'.hash_file('sha256', __FILE__);
        $cache->forget($cacheKey);
        $firstExtractor = $this->makeExtractor($parser, cache: $cache);
        $secondExtractor = $this->makeExtractor($parser, cache: $cache);

        $this->assertSame('OSUDO4A ASETA', $firstExtractor->extractRoute(__FILE__));
        $this->assertSame([
            'departure' => 'SBKP',
            'destination' => 'SCEL',
            'alternate' => 'SAME',
            'departure_airport' => null,
            'destination_airport' => null,
            'alternate_airport' => null,
            'initial_altitude' => 'FL 360',
            'duration' => '03h22m',
            'route' => 'OSUDO4A ASETA',
        ], $secondExtractor->extractFlightPlanData(__FILE__));
        $this->assertSame($pdfText, $cache->get($cacheKey));
    }

    public function test_pdf_text_cache_is_invalidated_when_file_contents_change_at_the_same_path(): void
    {
        $path = tempnam('/tmp', 'flight-release-cache-');
        $this->assertIsString($path);

        $firstDocument = $this->createMock(Document::class);
        $firstDocument->expects($this->once())
            ->method('getText')
            ->willReturn("(FPL-CKS272-IS\n-N0487F360 OSUDO4A ASETA\n-SCEL0322)");

        $secondDocument = $this->createMock(Document::class);
        $secondDocument->expects($this->once())
            ->method('getText')
            ->willReturn("(FPL-CKS273-IS\n-N0487F360 DCT KEMAX UL9\n-SCEL0322)");

        $parser = $this->createMock(Parser::class);
        $parser->expects($this->exactly(2))
            ->method('parseFile')
            ->with($path)
            ->willReturnOnConsecutiveCalls($firstDocument, $secondDocument);

        $extractor = $this->makeExtractor($parser);

        try {
            file_put_contents($path, 'first PDF contents');
            $this->assertSame('OSUDO4A ASETA', $extractor->extractRoute($path));

            file_put_contents($path, 'different PDF contents');
            $this->assertSame('DCT KEMAX UL9', $extractor->extractRoute($path));
        } finally {
            unlink($path);
        }
    }

    public function test_container_injects_the_airport_lookup_client(): void
    {
        $lookups = [];
        $airportLookupClient = $this->createMock(AirportLookupClient::class);
        $airportLookupClient->expects($this->exactly(2))
            ->method('lookupByIcao')
            ->willReturnCallback(function (string $icao) use (&$lookups): ?AirportData {
                $lookups[] = $icao;

                return null;
            });
        $this->app->instance(AirportLookupClient::class, $airportLookupClient);

        $extractor = app(FlightRouteExtractor::class);
        $extractor->extractFlightPlanDataFromText(<<<'TEXT'
(FPL-CKS272-IS
-B77L/H-SDE2E3FGHIJ1J4J5M1P2RWXYZ/LB1D1G1
-SBKP1000
-N0487F360 OSUDO4A ASETA
-SCEL0322)
TEXT);

        $this->assertSame(['SBKP', 'SCEL'], $lookups);
    }

    public function test_extract_flight_plan_data_from_text_sets_alternate_to_null_when_not_listed(): void
    {
        $extractor = $this->makeExtractor();

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
        $this->assertNull($flightPlan['alternate_airport']);
        $this->assertSame('SCEL', $flightPlan['destination']);
        $this->assertSame('03h22m', $flightPlan['duration']);
    }

    public function test_extract_flight_plan_data_from_text_returns_null_airport_dtos_when_lookup_finds_no_match(): void
    {
        $extractor = $this->makeExtractor();

        $text = <<<'TEXT'
(FPL-CKS272-IS
-B77L/H-SDE2E3FGHIJ1J4J5M1P2RWXYZ/LB1D1G1
-SBKP1000
-N0487F360 OSUDO4A ASETA
-SCEL0322 SAME
-PBN/A1L1B1C1D1O1S2 NAV/Z1)
TEXT;

        $flightPlan = $extractor->extractFlightPlanDataFromText($text);

        $this->assertNull($flightPlan['departure_airport']);
        $this->assertNull($flightPlan['destination_airport']);
        $this->assertNull($flightPlan['alternate_airport']);
    }

    public function test_extract_route_from_text_throws_when_no_route_block_is_found(): void
    {
        $extractor = $this->makeExtractor();

        $this->expectException(FlightRouteNotFoundException::class);
        $this->expectExceptionMessage('No ICAO flight plan block was found in the uploaded PDF.');

        $extractor->extractRouteFromText('No flight plan route block is present.');
    }

    public function test_extract_route_from_text_throws_detailed_error_when_route_segment_is_missing(): void
    {
        $extractor = $this->makeExtractor();

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

        $extractor = $this->makeExtractor($parser);

        $this->expectException(FlightRouteNotFoundException::class);
        $this->expectExceptionMessage(
            'The uploaded PDF could not be read. Object list not found. Possible secured file.'
        );

        $extractor->extractRoute('/tmp/flight-release.pdf');
    }

    public function test_format_for_icao_display_wraps_route_on_token_boundaries(): void
    {
        $extractor = $this->makeExtractor();

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
        $extractor = $this->makeExtractor();

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

    private function makeExtractor(
        ?Parser $parser = null,
        ?AirportLookupClient $airportLookupClient = null,
        ?Repository $cache = null,
    ): FlightRouteExtractor {
        return new FlightRouteExtractor(
            $parser ?? new Parser,
            $airportLookupClient ?? $this->fakeAirportLookupClient(),
            $cache ?? new CacheRepository(new ArrayStore),
        );
    }

    /**
     * @param  array<string, AirportData>  $airports
     */
    private function fakeAirportLookupClient(array $airports = []): AirportLookupClient
    {
        $client = $this->createMock(AirportLookupClient::class);
        $client->method('lookupByIcao')
            ->willReturnCallback(static fn (string $icao): ?AirportData => $airports[$icao] ?? null);

        return $client;
    }
}
