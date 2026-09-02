<?php

namespace Tests\Unit;

use App\Exceptions\FlightRouteNotFoundException;
use App\Services\FlightPlan\Extractor\FlightPlanTextExtractor;
use App\Services\FlightPlan\Extractor\GeneralDeclarationExtractor;
use App\Services\FlightPlan\Extractor\PdfImagePageTextExtractor;
use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Mockery\VerificationDirector;
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
            $first = new FlightPlanTextExtractor($parser, $cache, new PdfImagePageTextExtractor);
            $second = new FlightPlanTextExtractor($parser, $cache, new PdfImagePageTextExtractor);

            $this->assertSame('FLIGHT PLAN', $first->extract($path));
            $this->assertSame('FLIGHT PLAN', $second->extract($path));
        } finally {
            unlink($path);
        }
    }

    public function test_it_translates_pdf_parser_failures(): void
    {
        $log = Log::spy();

        $parser = $this->createMock(Parser::class);
        $parser->expects($this->once())
            ->method('parseFile')
            ->willThrowException(new \RuntimeException('private parser detail /private/upload/path.pdf'));

        $extractor = new FlightPlanTextExtractor(
            $parser,
            new Repository(new ArrayStore),
            new PdfImagePageTextExtractor,
        );

        $this->expectException(FlightRouteNotFoundException::class);
        $this->expectExceptionMessage('The uploaded PDF could not be read. It may be malformed, secured, or image-only.');

        try {
            $extractor->extract('/tmp/missing-flight-plan.pdf');
        } finally {
            $this->assertReceivedOnce($log, 'error')->with('PDF parsing failed', [
                'error_code' => \RuntimeException::class,
            ]);
        }
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

    public function test_it_records_safe_pdf_page_and_ocr_timing_context(): void
    {
        $path = tempnam('/tmp', 'flight-plan-text-');
        $this->assertIsString($path);
        file_put_contents($path, 'pdf bytes');

        $textPage = $this->createMock(Page::class);
        $textPage->method('getText')->willReturn('FLIGHT PLAN');
        $imagePage = $this->createMock(Page::class);
        $imagePage->method('getText')->willReturn('');
        $document = $this->createMock(Document::class);
        $document->method('getText')->willReturn('PRIVATE DOCUMENT CONTENT');
        $document->method('getPages')->willReturn([$textPage, $imagePage]);
        $parser = $this->createMock(Parser::class);
        $parser->method('parseFile')->with($path)->willReturn($document);
        $imagePageTextExtractor = $this->createMock(PdfImagePageTextExtractor::class);
        $imagePageTextExtractor->method('extract')->with($path, 1)->willReturn('PRIVATE OCR CONTENT');

        $timings = [];
        $debugbar = $this->createMock(LaravelDebugbar::class);
        $debugbar->method('isCollecting')->willReturn(true);
        $debugbar->expects($this->exactly(4))
            ->method('addMeasure')
            ->willReturnCallback(function (
                string $label,
                float $start,
                ?float $end,
                array $context,
                ?string $collector,
                ?string $group,
            ) use (&$timings): void {
                $timings[] = compact('label', 'context', 'collector', 'group');
            });
        $this->app->instance(LaravelDebugbar::class, $debugbar);

        try {
            $extractor = new FlightPlanTextExtractor(
                $parser,
                new Repository(new ArrayStore),
                $imagePageTextExtractor,
            );

            $extractor->extract($path);

            $this->assertSame([
                ['operation' => 'parse_file'],
                ['operation' => 'page_text', 'page_index' => 0, 'page_number' => 1, 'ocr_required' => false],
                ['operation' => 'page_text', 'page_index' => 1, 'page_number' => 2, 'ocr_required' => true],
                ['operation' => 'ocr', 'page_index' => 1, 'page_number' => 2, 'ocr_required' => true],
            ], array_column($timings, 'context'));
            $this->assertSame([
                'Flight plan PDF parse',
                'Flight plan page text extraction',
                'Flight plan page text extraction',
                'Flight plan page OCR',
            ], array_column($timings, 'label'));
            $this->assertSame(['time'], array_values(array_unique(array_column($timings, 'collector'))));
            $this->assertSame(['Flight plan extraction'], array_values(array_unique(array_column($timings, 'group'))));
            $this->assertStringNotContainsString('PRIVATE', serialize($timings));
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
            new PdfImagePageTextExtractor,
        ))->extract($path);

        $result = (new GeneralDeclarationExtractor)->extract($text);

        $this->assertTrue($result['data']['section_present']);
    }

    private function assertReceivedOnce(MockInterface $mock, string $method): VerificationDirector
    {
        return $mock->shouldHaveReceived($method)->once();
    }
}
