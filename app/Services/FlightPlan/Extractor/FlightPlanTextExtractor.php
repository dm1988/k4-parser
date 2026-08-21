<?php

namespace App\Services\FlightPlan\Extractor;

use App\Exceptions\FlightRouteNotFoundException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use Throwable;

class FlightPlanTextExtractor
{
    public function __construct(
        private readonly Parser $parser,
        private readonly Repository $cache,
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

        return $fileHash === false ? null : 'flight-plan-extractor:pdf-text:'.$fileHash;
    }

    private function read(string $filePath): string
    {
        try {
            return str_replace("\x00", '', $this->parser->parseFile($filePath)->getText());
        } catch (Throwable $throwable) {
            try {
                Log::error('PDF parsing failed', [
                    'file' => $filePath,
                    'error' => $throwable->getMessage(),
                ]);
            } catch (Throwable) {
                // Logging is best-effort when the Laravel container is unavailable.
            }

            throw FlightRouteNotFoundException::pdfCouldNotBeRead($throwable->getMessage());
        }
    }
}
