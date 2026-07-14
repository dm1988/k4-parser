<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\Process\Process;
use Throwable;

class RosterSourceResolver
{
    public const PDF_TYPE_PUBLISHED_ROSTER = 'published_roster';

    public const PDF_TYPE_TRIP_INFORMATION = 'trip_information';

    public function __construct(
        private readonly PdfScheduleParser $pdfScheduleParser,
    ) {}

    public function resolve(?UploadedFile $file, ?string $text): array
    {
        if ($file === null) {
            $rawText = $text ?? '';

            return [
                'source' => 'text',
                'document_type' => null,
                'file' => null,
                'mime' => null,
                'raw_text' => $rawText,
                'meta' => null,
            ];
        }

        $mime = $file->getMimeType();

        if ($mime === 'application/pdf') {
            $tmpPath = $file->getRealPath();

            if (! is_string($tmpPath) || ! is_file($tmpPath)) {
                throw ValidationException::withMessages([
                    'file' => 'The uploaded PDF could not be read.',
                ]);
            }

            $pdfData = $this->pdfScheduleParser->parse($tmpPath);
            $rawText = trim($pdfData['text'] ?? '');

            if ($rawText === '') {
                throw ValidationException::withMessages([
                    'file' => 'No readable text was found in the PDF.',
                ]);
            }

            $documentType = $this->detectPdfType($rawText);

            return [
                'source' => 'pdf',
                'document_type' => $documentType,
                'file' => null,
                'mime' => $mime,
                'raw_text' => $rawText,
                'meta' => $this->normalizePdfMeta($pdfData),
            ];
        }

        $rawText = $this->extractTextFromImage($file->getRealPath());

        return [
            'source' => 'image',
            'document_type' => null,
            'file' => null,
            'mime' => $mime,
            'raw_text' => $rawText,
            'meta' => null,
        ];
    }

    private function detectPdfType(string $rawText): string
    {
        $text = $this->normalizeTextForDetection($rawText);

        /*
        * The title receives the highest score, but secondary markers provide
        * a fallback if the PDF extraction splits or misses the heading.
        */
        $scores = [
            self::PDF_TYPE_PUBLISHED_ROSTER => $this->scoreMarkers($text, [
                'published roster' => 10,
                'planning period' => 3,
                'report (utc)' => 2,
                'off days' => 2,
                'block time + dh time' => 2,
                'qualifications' => 1,
            ]),

            self::PDF_TYPE_TRIP_INFORMATION => $this->scoreMarkers($text, [
                'trip information' => 10,
                'operates' => 3,
                'duty summary' => 3,
                'trip summary' => 3,
                'crew on trip' => 2,
                'annotations on trip' => 2,
                'departure-arrival' => 1,
            ]),
        ];

        $highestScore = max($scores);

        $matches = array_keys(
            array_filter(
                $scores,
                fn (int $score): bool => $score === $highestScore,
            ),
        );

        /*
        * A score of 8 allows classification without the main heading, but
        * still requires several format-specific markers.
        */
        if ($highestScore < 8 || count($matches) !== 1) {
            throw ValidationException::withMessages([
                'file' => 'The PDF type could not be identified. Upload either a Published Roster or Trip Information PDF.',
            ]);
        }

        return $matches[0];
    }

    /**
     * @param  array<string, int>  $markers
     */
    private function scoreMarkers(string $text, array $markers): int
    {
        $score = 0;

        foreach ($markers as $marker => $weight) {
            if (str_contains($text, $marker)) {
                $score += $weight;
            }
        }

        return $score;
    }

    private function normalizeTextForDetection(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace("\u{00A0}", ' ', $text);

        return preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    }

    private function normalizePdfMeta(array $pdfData): ?array
    {
        $meta = [
            'trip_id' => $pdfData['pdf_meta']['trip_id']
                ?? $pdfData['trip_id']
                ?? $pdfData['parsed']['trip']['trip_number']
                ?? null,
            'date' => $pdfData['pdf_meta']['date']
                ?? $pdfData['date']
                ?? null,
            'page_count' => $pdfData['pdf_meta']['page_count'] ?? null,
        ];

        $meta = array_filter($meta, fn ($value) => $value !== null && $value !== '');

        return $meta === [] ? null : $meta;
    }

    private function extractTextFromImage(string $path): string
    {
        $tesseract = config('services.ocr.tesseract_path', '/usr/bin/tesseract');

        if (! is_executable($tesseract)) {
            throw ValidationException::withMessages([
                'image' => "OCR is not installed in the web server container. Expected Tesseract at {$tesseract}.",
            ]);
        }

        // Check cache for OCR results using file hash to avoid reprocessing identical images
        $fileHash = md5_file($path);
        $cacheKey = "ocr_result:{$fileHash}";

        $cachedText = cache()->get($cacheKey);
        if ($cachedText !== null && is_string($cachedText)) {
            return $cachedText;
        }

        // --- PREPROCESSING STEP ---
        // Create a temporary path for the optimized image
        $optimizedPath = storage_path('app/ocr_temp_'.uniqid().'.jpg');

        try {
            $image = Image::read($path);
            $originalWidth = $image->width();
            $originalHeight = $image->height();

            // Skip preprocessing for already high-resolution images (1000x800+)
            $needsPreprocessing = $originalWidth < 1000 || $originalHeight < 800;

            if ($needsPreprocessing) {
                // 1. Convert to greyscale
                $image->greyscale();

                // 2. Adaptive upscaling: only upscale if image is small (< 400px wide)
                if ($originalWidth < 400) {
                    $image->resize($originalWidth * 2, $originalHeight * 2);
                }

                // 3. Boost contrast to make text pop against backgrounds
                $image->contrast(15);
            }

            // Save as JPEG for faster I/O (better compression than PNG)
            $image->toJpeg(quality: 85)->save($optimizedPath);
        } catch (Throwable $e) {
            // Fallback to original path if preprocessing fails
            $optimizedPath = $path;
        }
        // ---------------------------

        // Use $optimizedPath instead of $path
        $process = new Process([
            $tesseract,
            $optimizedPath,
            'stdout',
            '--psm', '6',
        ]);
        $process->setTimeout(30);

        try {
            $process->mustRun();
            $text = trim($process->getOutput());
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'image' => 'OCR failed. Try a sharper roster screenshot or paste the extracted text instead.',
            ]);
        } finally {
            // Pass both paths. The variadic helper will safely deduplicate them
            // if $optimizedPath fell back to $path.
            $this->cleanupTempFiles($optimizedPath, $path);
        }

        if ($text === '') {
            throw ValidationException::withMessages([
                'image' => 'OCR did not find any text in that image. Try a clearer screenshot or paste the text manually.',
            ]);
        }

        // Cache the result for 30 days
        cache()->put($cacheKey, $text, now()->addDays(30));

        return $text;
    }

    /**
     * Clean up any generated temporary files safely.
     */
    private function cleanupTempFiles(string ...$paths): void
    {
        foreach (array_unique($paths) as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
