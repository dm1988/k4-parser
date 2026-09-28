<?php

namespace App\Services\FlightPlan\Extractor;

use App\Exceptions\FlightRouteNotFoundException;
use Closure;
use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Throwable;

class FlightPlanTextExtractor
{
    public function __construct(
        private readonly Parser $parser,
        private readonly Repository $cache,
        private readonly PdfImagePageTextExtractor $imagePageTextExtractor,
    ) {}

    /** @param  (Closure(string): void)|null  $onProgress */
    public function extract(string $filePath, ?Closure $onProgress = null): string
    {
        $cacheKey = $this->cacheKey($filePath);

        if ($cacheKey === null) {
            return $this->read($filePath, $onProgress);
        }

        $readFromPdf = false;
        $text = $this->cache->remember(
            $cacheKey,
            now()->addDays(7),
            function () use ($filePath, $onProgress, &$readFromPdf): string {
                $readFromPdf = true;

                return $this->read($filePath, $onProgress);
            },
        );

        if (! $readFromPdf) {
            $onProgress?->__invoke('Previously extracted text loaded.');
        }

        return $text;
    }

    private function cacheKey(string $filePath): ?string
    {
        if (! is_file($filePath)) {
            return null;
        }

        $fileHash = hash_file('sha256', $filePath);

        return $fileHash === false ? null : 'flight-plan-extractor:v3:pdf-text:'.$fileHash;
    }

    /** @param  (Closure(string): void)|null  $onProgress */
    private function read(string $filePath, ?Closure $onProgress): string
    {
        try {
            $onProgress?->__invoke('Reading PDF…');
            $parseStartedAt = microtime(true);

            try {
                $document = $this->parser->parseFile($filePath);
            } finally {
                $this->recordTiming('Flight plan PDF parse', $parseStartedAt, [
                    'operation' => 'parse_file',
                ]);
            }

            $pages = $document->getPages();

            if ($pages === []) {
                $onProgress?->__invoke('Extracting text…');

                return str_replace("\x00", '', $document->getText());
            }

            $pageTexts = [];
            $ocrTexts = [];
            $pageCount = count($pages);
            $previousPageUsedOcr = false;

            foreach ($pages as $pageIndex => $page) {
                $pageNumber = $pageIndex + 1;
                $pageTextStartedAt = microtime(true);
                $ocrRequired = null;

                if ($pageNumber === 1 || $pageNumber % 10 === 0 || $pageNumber === $pageCount || $previousPageUsedOcr) {
                    $onProgress?->__invoke("Extracting text — page {$pageNumber} of {$pageCount}…");
                }

                try {
                    $pageText = str_replace("\x00", '', $page->getText());
                    $ocrRequired = Str::squish($pageText) === '';
                } finally {
                    $this->recordTiming('Flight plan page text extraction', $pageTextStartedAt, [
                        'operation' => 'page_text',
                        'page_index' => $pageIndex,
                        'page_number' => $pageNumber,
                        'ocr_required' => $ocrRequired,
                    ]);
                }

                $previousPageUsedOcr = $ocrRequired;

                if (! $ocrRequired) {
                    $pageTexts[] = trim($pageText);

                    continue;
                }

                $ocrStartedAt = microtime(true);
                $onProgress?->__invoke("Extracting text from images — page {$pageNumber} of {$pageCount}…");

                try {
                    $ocrText = $this->imagePageTextExtractor->extract($filePath, $pageIndex);
                } finally {
                    $this->recordTiming('Flight plan page OCR', $ocrStartedAt, [
                        'operation' => 'ocr',
                        'page_index' => $pageIndex,
                        'page_number' => $pageNumber,
                        'ocr_required' => true,
                    ]);
                }

                if ($ocrText !== '') {
                    $ocrTexts[] = $ocrText;
                }
            }

            $text = implode("\n\n", $pageTexts);

            foreach ($ocrTexts as $ocrText) {
                $text .= "\n".$ocrText;
            }

            return $text;
        } catch (FlightRouteNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            try {
                Log::error('PDF parsing failed', [
                    'error_code' => $throwable::class,
                ]);
            } catch (Throwable) {
                // Logging is best-effort when the Laravel container is unavailable.
            }

            throw FlightRouteNotFoundException::pdfCouldNotBeRead();
        }
    }

    /**
     * @param  array<string, bool|int|string|null>  $context
     */
    private function recordTiming(string $label, float $startedAt, array $context): void
    {
        try {
            if (! class_exists(LaravelDebugbar::class) || ! app()->bound(LaravelDebugbar::class)) {
                return;
            }

            $debugbar = app(LaravelDebugbar::class);

            if (! $debugbar->isCollecting()) {
                return;
            }

            $debugbar->addMeasure(
                $label,
                $startedAt,
                microtime(true),
                $context,
                'time',
                'Flight plan extraction',
            );
        } catch (Throwable) {
        }
    }
}
