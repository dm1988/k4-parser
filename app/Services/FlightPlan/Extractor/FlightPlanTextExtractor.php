<?php

namespace App\Services\FlightPlan\Extractor;

use App\Exceptions\FlightRouteNotFoundException;
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

    public function extract(string $filePath): string
    {
        $cacheKey = $this->cacheKey($filePath);

        if ($cacheKey === null) {
            return $this->read($filePath);
        }

        return $this->cache->remember(
            $cacheKey,
            now()->addDays(7),
            fn (): string => $this->read($filePath),
        );
    }

    private function cacheKey(string $filePath): ?string
    {
        if (! is_file($filePath)) {
            return null;
        }

        $fileHash = hash_file('sha256', $filePath);

        return $fileHash === false ? null : 'flight-plan-extractor:v2:pdf-text:'.$fileHash;
    }

    private function read(string $filePath): string
    {
        try {
            $parseStartedAt = microtime(true);

            try {
                $document = $this->parser->parseFile($filePath);
            } finally {
                $this->recordTiming('Flight plan PDF parse', $parseStartedAt, [
                    'operation' => 'parse_file',
                ]);
            }

            $text = str_replace("\x00", '', $document->getText());

            foreach ($document->getPages() as $pageIndex => $page) {
                $pageNumber = $pageIndex + 1;
                $pageTextStartedAt = microtime(true);
                $ocrRequired = null;

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

                if (! $ocrRequired) {
                    continue;
                }

                $ocrStartedAt = microtime(true);

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
                    $text .= "\n".$ocrText;
                }
            }

            return $text;
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
