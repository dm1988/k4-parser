<?php

namespace Tests\Unit;

use App\Exceptions\FlightRouteNotFoundException;
use App\Services\FlightPlan\Extractor\FlightPlanTextExtractor;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class FlightPlanTextExtractorTest extends TestCase
{
    public function test_it_reads_sanitizes_and_caches_pdf_text_by_content(): void
    {
        $path = tempnam('/tmp', 'flight-plan-text-');
        $this->assertIsString($path);
        file_put_contents($path, 'pdf bytes');

        $document = $this->createMock(Document::class);
        $document->expects($this->once())->method('getText')->willReturn("FLIGHT\x00 PLAN");
        $parser = $this->createMock(Parser::class);
        $parser->expects($this->once())->method('parseFile')->with($path)->willReturn($document);
        $cache = new Repository(new ArrayStore);

        try {
            $first = new FlightPlanTextExtractor($parser, $cache);
            $second = new FlightPlanTextExtractor($parser, $cache);

            $this->assertSame('FLIGHT PLAN', $first->extract($path));
            $this->assertSame('FLIGHT PLAN', $second->extract($path));
        } finally {
            unlink($path);
        }
    }

    public function test_it_translates_pdf_parser_failures(): void
    {
        $parser = $this->createMock(Parser::class);
        $parser->expects($this->once())
            ->method('parseFile')
            ->willThrowException(new \RuntimeException('secured file'));

        $extractor = new FlightPlanTextExtractor($parser, new Repository(new ArrayStore));

        $this->expectException(FlightRouteNotFoundException::class);
        $this->expectExceptionMessage('The uploaded PDF could not be read. secured file');

        $extractor->extract('/tmp/missing-flight-plan.pdf');
    }
}
