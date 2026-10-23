<?php

namespace Tests\Unit;

use App\Exceptions\FlightRouteNotFoundException;
use App\Services\FlightPlan\Extractor\PdfImagePageTextExtractor;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Tests\TestCase;

class PdfImagePageTextExtractorTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_it_renders_the_selected_one_based_page_and_reads_its_png(): void
    {
        $argumentLog = $this->temporaryFile();
        $pdftoppm = $this->executable(
            'printf "%s\n" "$@" > '.escapeshellarg($argumentLog)."\n".
            'for argument in "$@"; do output="$argument"; done'."\n".
            'printf png > "$output.png"'."\n",
        );
        $tesseract = $this->executable(
            '[ -f "$1" ] && [ "$2" = stdout ] && [ "$3" = --psm ] && [ "$4" = 6 ] || exit 1'."\n".
            'printf "  FLIGHT PLAN  \\n"'."\n",
        );
        config()->set('services.ocr.pdftoppm_path', $pdftoppm);
        config()->set('services.ocr.tesseract_path', $tesseract);
        $before = $this->ocrTemporaryFiles();

        $this->assertSame('FLIGHT PLAN', (new PdfImagePageTextExtractor)->extract('/tmp/release.pdf', 1));

        $arguments = file($argumentLog, FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($arguments);
        $this->assertSame(['-f', '2', '-l', '2', '-singlefile', '-r', '200', '-png', '/tmp/release.pdf'], array_slice($arguments, 0, 9));
        $this->assertSame($before, $this->ocrTemporaryFiles());
    }

    public function test_it_reports_missing_poppler_as_unavailable(): void
    {
        config()->set('services.ocr.pdftoppm_path', '/missing/pdftoppm');

        $this->expectException(FlightRouteNotFoundException::class);
        $this->expectExceptionMessage('OCR is unavailable');

        (new PdfImagePageTextExtractor)->extract('/tmp/release.pdf', 0);
    }

    public function test_it_rejects_negative_page_indexes(): void
    {
        config()->set('services.ocr.pdftoppm_path', $this->executable('exit 0'));
        config()->set('services.ocr.tesseract_path', $this->executable('exit 0'));

        $this->expectException(FlightRouteNotFoundException::class);
        $this->expectExceptionMessage('could not be read by OCR');

        (new PdfImagePageTextExtractor)->extract('/tmp/release.pdf', -1);
    }

    public function test_it_cleans_up_partial_pngs_after_rendering_fails(): void
    {
        $log = Log::spy();
        $pdftoppm = $this->executable(
            'for argument in "$@"; do output="$argument"; done'."\n".
            'printf partial > "$output.png"'."\n".
            'printf "PRIVATE RELEASE CONTENT /private/release.pdf" >&2'."\n".
            'exit 2'."\n",
        );
        config()->set('services.ocr.pdftoppm_path', $pdftoppm);
        config()->set('services.ocr.tesseract_path', $this->executable('exit 0'));
        $before = $this->ocrTemporaryFiles();

        try {
            (new PdfImagePageTextExtractor)->extract('/tmp/release.pdf', 0);
            $this->fail('Expected OCR failure was not thrown.');
        } catch (FlightRouteNotFoundException $exception) {
            $this->assertSame('An image-only page in the uploaded PDF could not be read by OCR.', $exception->getMessage());
        }

        $this->assertSame($before, $this->ocrTemporaryFiles());
        $log->shouldHaveReceived('error')->once()->with('Flight plan image page extraction failed', [
            'stage' => 'rasterization',
            'page_number' => 1,
            'error_code' => ProcessFailedException::class,
            'exit_code' => 2,
        ]);
    }

    public function test_it_fails_when_poppler_does_not_write_a_png(): void
    {
        config()->set('services.ocr.pdftoppm_path', $this->executable('exit 0'));
        config()->set('services.ocr.tesseract_path', $this->executable('exit 0'));
        $before = $this->ocrTemporaryFiles();

        try {
            (new PdfImagePageTextExtractor)->extract('/tmp/release.pdf', 0);
            $this->fail('Expected OCR failure was not thrown.');
        } catch (FlightRouteNotFoundException $exception) {
            $this->assertSame('An image-only page in the uploaded PDF could not be read by OCR.', $exception->getMessage());
        }

        $this->assertSame($before, $this->ocrTemporaryFiles());
    }

    private function temporaryFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ocr-test-');
        $this->assertIsString($path);
        $this->temporaryPaths[] = $path;

        return $path;
    }

    private function executable(string $commands): string
    {
        $path = $this->temporaryFile();
        file_put_contents($path, "#!/bin/sh\n{$commands}\n");
        chmod($path, 0700);

        return $path;
    }

    /** @return list<string> */
    private function ocrTemporaryFiles(): array
    {
        return glob(storage_path('app/flight-plan-ocr-*')) ?: [];
    }
}
