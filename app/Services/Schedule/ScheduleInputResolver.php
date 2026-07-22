<?php

namespace App\Services\Schedule;

use App\Enums\ScheduleDocumentType;
use App\Services\Schedule\Extractor\PdfTextExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\Process\Process;
use Throwable;

class ScheduleInputResolver
{
    public function __construct(
        private readonly PdfTextExtractor $pdfTextExtractor,
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
        $path = $file->getRealPath();

        if (! is_string($path) || ! is_file($path)) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded schedule could not be read.',
            ]);
        }

        if ($mime === 'application/pdf') {
            $pdfData = $this->pdfTextExtractor->extract($path);
            $rawText = trim($pdfData['text']);

            if ($rawText === '') {
                throw ValidationException::withMessages([
                    'file' => 'No readable text was found in the PDF.',
                ]);
            }

            $documentType = $this->detectPdfType($rawText);

            return [
                'source' => 'pdf',
                'document_type' => $documentType->value,
                'file' => null,
                'mime' => $mime,
                'raw_text' => $rawText,
                'meta' => $this->normalizePdfMeta($pdfData),
            ];
        }

        $rawText = $this->extractTextFromImage($path);

        return [
            'source' => 'image',
            'document_type' => null,
            'file' => null,
            'mime' => $mime,
            'raw_text' => $rawText,
            'meta' => null,
        ];
    }

    private function detectPdfType(string $rawText): ScheduleDocumentType
    {
        $text = $this->normalizeTextForDetection($rawText);

        /*
        * The title receives the highest score, but secondary markers provide
        * a fallback if the PDF extraction splits or misses the heading.
        */
        $scores = [
            ScheduleDocumentType::PublishedRoster->value => $this->scoreMarkers($text, [
                'published roster' => 10,
                'planning period' => 3,
                'report (utc)' => 2,
                'off days' => 2,
                'block time + dh time' => 2,
                'qualifications' => 1,
            ]),

            ScheduleDocumentType::TripInformation->value => $this->scoreMarkers($text, [
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

        return ScheduleDocumentType::from($matches[0]);
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
            'page_count' => $pdfData['pdf_meta']['page_count'] ?? null,
        ];

        $meta = array_filter($meta, fn ($value) => $value !== null && $value !== '');

        return $meta === [] ? null : $meta;
    }

    private function extractTextFromImage(string $path): string
    {
        $cacheKey = 'roster-source-resolver:ocr-text:'.$this->fileHash($path);

        $cachedText = cache()->get($cacheKey);

        if (is_string($cachedText) && $cachedText !== '') {
            return $cachedText;
        }

        $tesseract = config('services.ocr.tesseract_path', '/usr/bin/tesseract');

        if (! is_executable($tesseract)) {
            throw ValidationException::withMessages([
                'file' => "OCR is not installed in the web server container. Expected Tesseract at {$tesseract}.",
            ]);
        }

        $optimizedPath = tempnam(storage_path('app'), 'ocr-');

        if ($optimizedPath === false) {
            throw ValidationException::withMessages([
                'file' => 'A temporary OCR image could not be created. Try the upload again.',
            ]);
        }

        try {
            $ocrInputPath = $this->prepareImageForOcr($path, $optimizedPath);
            $text = $this->runOcr($tesseract, $ocrInputPath);
        } finally {
            $this->cleanupGeneratedFile($optimizedPath);
        }

        if ($text === '') {
            throw ValidationException::withMessages([
                'file' => 'OCR did not find any text in that image. Try a clearer screenshot or paste the text manually.',
            ]);
        }

        cache()->put($cacheKey, $text, now()->addDays(30));

        return $text;
    }

    private function fileHash(string $path): string
    {
        $fileHash = hash_file('sha256', $path);

        if ($fileHash === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded image could not be read for OCR.',
            ]);
        }

        return $fileHash;
    }

    private function prepareImageForOcr(string $sourcePath, string $optimizedPath): string
    {
        try {
            $image = Image::decodePath($sourcePath);
            $originalWidth = $image->width();
            $originalHeight = $image->height();

            if ($originalWidth < 1000 || $originalHeight < 800) {
                $image->grayscale();

                if ($originalWidth < 400) {
                    $image->resize($originalWidth * 2, $originalHeight * 2);
                }

                $image->contrast(15);
            }

            $image->encode(new JpegEncoder(quality: 85))->save($optimizedPath);

            return $optimizedPath;
        } catch (Throwable) {
            return $sourcePath;
        }
    }

    private function runOcr(string $tesseract, string $path): string
    {
        $process = new Process([
            $tesseract,
            $path,
            'stdout',
            '--psm', '6',
        ]);
        $process->setTimeout(30);

        try {
            $process->mustRun();

            return trim($process->getOutput());
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'file' => 'OCR failed. Try a sharper roster screenshot or paste the extracted text instead.',
            ]);
        }
    }

    private function cleanupGeneratedFile(string $path): void
    {
        File::delete($path);
    }
}
