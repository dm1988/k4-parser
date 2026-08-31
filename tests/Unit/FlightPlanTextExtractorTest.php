<?php

namespace Tests\Unit;

use App\Exceptions\FlightRouteNotFoundException;
use App\Services\FlightPlan\Extractor\FlightPlanTextExtractor;
use App\Services\FlightPlan\Extractor\GeneralDeclarationExtractor;
use App\Services\FlightPlan\Extractor\PdfImagePageTextExtractor;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Page;
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
        $document->expects($this->once())->method('getPages')->willReturn([]);
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

    public function test_it_appends_ocr_text_from_pages_without_extractable_text(): void
    {
        $path = tempnam('/tmp', 'flight-plan-text-');
        $this->assertIsString($path);
        file_put_contents($path, 'pdf bytes');

        $textPage = $this->createMock(Page::class);
        $textPage->expects($this->once())->method('getText')->willReturn('FLIGHT PLAN');
        $imagePage = $this->createMock(Page::class);
        $imagePage->expects($this->once())->method('getText')->willReturn("\n");

        $document = $this->createMock(Document::class);
        $document->expects($this->once())->method('getText')->willReturn('FLIGHT PLAN');
        $document->expects($this->once())->method('getPages')->willReturn([$textPage, $imagePage]);

        $parser = $this->createMock(Parser::class);
        $parser->expects($this->once())->method('parseFile')->with($path)->willReturn($document);

        $imagePageTextExtractor = $this->createMock(PdfImagePageTextExtractor::class);
        $imagePageTextExtractor->expects($this->once())
            ->method('extract')
            ->with($path, 1)
            ->willReturn('General Declaration (Outward/Inward)');

        try {
            $extractor = new FlightPlanTextExtractor(
                $parser,
                new Repository(new ArrayStore),
                $imagePageTextExtractor,
            );

            $this->assertSame(
                "FLIGHT PLAN\nGeneral Declaration (Outward/Inward)",
                $extractor->extract($path),
            );
        } finally {
            unlink($path);
        }
    }

    public function test_private_image_only_general_declaration_page_is_detected(): void
    {
        $path = storage_path('app/private/flight_releases/CKS027227SBKP.pdf');

        if (! is_file($path)) {
            $this->markTestSkipped('The private image-only GENDEC fixture is not available.');
        }

        $text = (new FlightPlanTextExtractor(
            new Parser,
            new Repository(new ArrayStore),
        ))->extract($path);

        $result = (new GeneralDeclarationExtractor)->extract($text);

        $this->assertTrue($result['data']['section_present']);
    }
}
