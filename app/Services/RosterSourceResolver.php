<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Throwable;

class RosterSourceResolver
{
    public function __construct(
        private readonly PdfScheduleParser $pdfScheduleParser,
    ) {
    }

    public function resolve(?UploadedFile $file, ?string $text): array
    {
        if ($file === null) {
            $rawText = $text ?? '';

            return [
                'source' => 'text',
                'file' => null,
                'mime' => null,
                'raw' => $rawText,
                'raw_text' => $rawText,
                'meta' => null,
            ];
        }

        $path = $file->store('uploads');
        $mime = $file->getMimeType();

        if ($mime === 'application/pdf') {
            $tmpPath = $file->getRealPath();
            $targetPath = ($tmpPath && file_exists($tmpPath)) ? $tmpPath : storage_path('app/' . $path);
            $pdfData = $this->pdfScheduleParser->parse($targetPath);
            $rawText = $pdfData['text'] ?? '';

            return [
                'source' => 'pdf',
                'file' => $path,
                'mime' => $mime,
                'raw' => $rawText,
                'raw_text' => $rawText,
                'meta' => $this->normalizePdfMeta($pdfData),
            ];
        }

        $rawText = $this->extractTextFromImage($file->getRealPath());

        return [
            'source' => 'image',
            'file' => $path,
            'mime' => $mime,
            'raw' => $rawText,
            'raw_text' => $rawText,
            'meta' => null,
        ];
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

        $process = new Process([
            $tesseract,
            $path,
            'stdout',
            '--psm',
            '6',
        ]);
        $process->setTimeout(30);

        try {
            $process->mustRun();
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'image' => 'OCR failed. Try a sharper roster screenshot or paste the extracted text instead.',
            ]);
        }

        $text = trim($process->getOutput());

        if ($text === '') {
            throw ValidationException::withMessages([
                'image' => 'OCR did not find any text in that image. Try a clearer screenshot or paste the text manually.',
            ]);
        }

        return $text;
    }
}
