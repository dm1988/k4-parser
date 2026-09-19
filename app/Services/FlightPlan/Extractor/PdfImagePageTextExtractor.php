<?php

namespace App\Services\FlightPlan\Extractor;

use App\Exceptions\FlightRouteNotFoundException;
use Illuminate\Support\Facades\File;
use Imagick;
use Symfony\Component\Process\Process;
use Throwable;

class PdfImagePageTextExtractor
{
    public function extract(string $pdfPath, int $pageIndex): string
    {
        $tesseract = config('services.ocr.tesseract_path', '/usr/bin/tesseract');

        if (! class_exists(Imagick::class) || ! is_string($tesseract) || ! is_executable($tesseract)) {
            throw FlightRouteNotFoundException::ocrUnavailable();
        }

        $imagePath = tempnam(storage_path('app'), 'flight-plan-ocr-');

        if ($imagePath === false) {
            throw FlightRouteNotFoundException::ocrFailed();
        }

        try {
            $this->renderPage($pdfPath, $pageIndex, $imagePath);

            $process = new Process([
                $tesseract,
                $imagePath,
                'stdout',
                '--psm', '6',
            ]);
            $process->setTimeout(30);
            $process->mustRun();

            return trim($process->getOutput());
        } catch (Throwable $throwable) {
            report($throwable);

            throw FlightRouteNotFoundException::ocrFailed();
        } finally {
            File::delete($imagePath);
        }
    }

    private function renderPage(string $pdfPath, int $pageIndex, string $imagePath): void
    {
        $image = new Imagick;

        try {
            $image->setResolution(200, 200);
            $image->readImage($pdfPath.'['.$pageIndex.']');
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $image->setImageFormat('png');
            $image->writeImage($imagePath);
        } finally {
            $image->clear();
        }
    }
}
