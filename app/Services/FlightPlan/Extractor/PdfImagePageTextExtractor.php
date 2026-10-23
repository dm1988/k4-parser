<?php

namespace App\Services\FlightPlan\Extractor;

use App\Exceptions\FlightRouteNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class PdfImagePageTextExtractor
{
    public function extract(string $pdfPath, int $pageIndex): string
    {
        $tesseract = config('services.ocr.tesseract_path', '/usr/bin/tesseract');
        $pdftoppm = config('services.ocr.pdftoppm_path', '/usr/bin/pdftoppm');

        if (! is_string($tesseract) || ! is_executable($tesseract)
            || ! is_string($pdftoppm) || ! is_executable($pdftoppm)) {
            throw FlightRouteNotFoundException::ocrUnavailable();
        }

        if ($pageIndex < 0) {
            throw FlightRouteNotFoundException::ocrFailed();
        }

        $temporaryFile = tempnam(storage_path('app'), 'flight-plan-ocr-');

        if ($temporaryFile === false) {
            throw FlightRouteNotFoundException::ocrFailed();
        }

        $imagePath = $temporaryFile.'.png';
        $stage = 'rasterization';

        try {
            $this->renderPage($pdftoppm, $pdfPath, $pageIndex, $temporaryFile);
            $stage = 'ocr';

            $process = new Process([
                $tesseract,
                $imagePath,
                'stdout',
                '--psm',
                '6',
            ]);
            $process->setTimeout(30);
            $process->mustRun();

            return trim($process->getOutput());
        } catch (Throwable $throwable) {
            Log::error('Flight plan image page extraction failed', [
                'stage' => $stage,
                'page_number' => $pageIndex + 1,
                'error_code' => $throwable::class,
                'exit_code' => $throwable instanceof ProcessFailedException
                    ? $throwable->getProcess()->getExitCode()
                    : null,
            ]);

            throw FlightRouteNotFoundException::ocrFailed();
        } finally {
            File::delete([$temporaryFile, $imagePath]);
        }
    }

    private function renderPage(string $pdftoppm, string $pdfPath, int $pageIndex, string $outputPrefix): void
    {
        $pageNumber = $pageIndex + 1;

        $process = new Process([
            $pdftoppm,
            '-f',
            (string) $pageNumber,
            '-l',
            (string) $pageNumber,
            '-singlefile',
            '-r',
            '200',
            '-png',
            $pdfPath,
            $outputPrefix,
        ]);
        $process->setTimeout(60);
        $process->mustRun();

        if (! is_file($outputPrefix.'.png')) {
            throw FlightRouteNotFoundException::ocrFailed();
        }
    }
}
