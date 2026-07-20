<?php

namespace Tests\Unit;

use App\Enums\ScheduleDocumentType;
use App\Services\ScheduleInputResolver;
use App\Services\SchedulePdfExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class ScheduleInputResolverTest extends TestCase
{
    /** @var list<string> */
    private array $tesseractScriptPaths = [];

    public function test_it_uses_the_pdf_extractor_and_returns_only_generic_pdf_metadata(): void
    {
        $file = UploadedFile::fake()->create('schedule.pdf', 100, 'application/pdf');

        $this->mock(SchedulePdfExtractor::class, function (MockInterface $mock) use ($file): void {
            $mock->shouldReceive('extract')
                ->once()
                ->with($file->getRealPath())
                ->andReturn([
                    'file' => $file->getRealPath(),
                    'text' => 'Trip Information Duty Summary Crew on trip',
                    'pdf_meta' => ['page_count' => 2],
                ]);
        });

        $source = app(ScheduleInputResolver::class)->resolve($file, null);

        $this->assertSame('pdf', $source['source']);
        $this->assertSame(ScheduleDocumentType::TripInformation->value, $source['document_type']);
        $this->assertSame('Trip Information Duty Summary Crew on trip', $source['raw_text']);
        $this->assertSame(['page_count' => 2], $source['meta']);
    }

    public function test_it_cleans_up_generated_ocr_temp_files_when_ocr_fails(): void
    {
        config()->set('services.ocr.tesseract_path', $this->failingTesseractScript());

        $before = $this->ocrTempFiles();
        $file = UploadedFile::fake()->image('roster.png', 300, 200);
        $sourcePath = $file->getRealPath();

        try {
            app(ScheduleInputResolver::class)->resolve($file, null);
            $this->fail('Expected OCR failure was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['OCR failed. Try a sharper roster screenshot or paste the extracted text instead.'],
                $exception->errors()['file'] ?? [],
            );
        }

        $this->assertSame($before, $this->ocrTempFiles());
        $this->assertFileExists($sourcePath);
    }

    public function test_it_uses_a_sha256_cache_key_and_reuses_cached_ocr_text(): void
    {
        config()->set('services.ocr.tesseract_path', $this->successfulTesseractScript());

        $before = $this->ocrTempFiles();
        $file = UploadedFile::fake()->image('roster.png', 300, 200);
        $sourcePath = $file->getRealPath();
        $cacheKey = 'roster-source-resolver:ocr-text:'.hash_file('sha256', $sourcePath);

        $result = app(ScheduleInputResolver::class)->resolve($file, null);

        $this->assertSame("Trip Information\nDuty Summary", $result['raw_text']);
        $this->assertSame("Trip Information\nDuty Summary", Cache::get($cacheKey));
        $this->assertSame($before, $this->ocrTempFiles());
        $this->assertFileExists($sourcePath);

        config()->set('services.ocr.tesseract_path', $this->failingTesseractScript());

        $cachedResult = app(ScheduleInputResolver::class)->resolve($file, null);

        $this->assertSame($result['raw_text'], $cachedResult['raw_text']);
        $this->assertFileExists($sourcePath);
    }

    public function test_it_passes_a_preprocessed_image_to_ocr(): void
    {
        config()->set('services.ocr.tesseract_path', $this->optimizedImageTesseractScript());

        $before = $this->ocrTempFiles();
        $file = UploadedFile::fake()->image('roster.png', 300, 200);
        $sourcePath = $file->getRealPath();

        $result = app(ScheduleInputResolver::class)->resolve($file, null);

        $this->assertSame("Trip Information\nDuty Summary", $result['raw_text']);
        $this->assertSame($before, $this->ocrTempFiles());
        $this->assertFileExists($sourcePath);
    }

    public function test_it_cleans_up_after_preprocessing_fallback_and_preserves_the_upload(): void
    {
        config()->set('services.ocr.tesseract_path', $this->successfulTesseractScript());

        $before = $this->ocrTempFiles();
        $file = UploadedFile::fake()->createWithContent('roster.png', 'not an image');
        $sourcePath = $file->getRealPath();

        $result = app(ScheduleInputResolver::class)->resolve($file, null);

        $this->assertSame("Trip Information\nDuty Summary", $result['raw_text']);
        $this->assertSame($before, $this->ocrTempFiles());
        $this->assertFileExists($sourcePath);
    }

    public function test_empty_ocr_output_uses_the_visible_file_error_and_cleans_up(): void
    {
        config()->set('services.ocr.tesseract_path', $this->emptyTesseractScript());

        $before = $this->ocrTempFiles();
        $file = UploadedFile::fake()->image('roster.png', 300, 200);
        $sourcePath = $file->getRealPath();

        try {
            app(ScheduleInputResolver::class)->resolve($file, null);
            $this->fail('Expected empty OCR validation failure was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['OCR did not find any text in that image. Try a clearer screenshot or paste the text manually.'],
                $exception->errors()['file'] ?? [],
            );
        }

        $this->assertSame($before, $this->ocrTempFiles());
        $this->assertFileExists($sourcePath);
    }

    private function failingTesseractScript(): string
    {
        return $this->tesseractScript("#!/bin/sh\nexit 1\n");
    }

    private function successfulTesseractScript(): string
    {
        return $this->tesseractScript("#!/bin/sh\nprintf 'Trip Information\\nDuty Summary\\n'\n");
    }

    private function optimizedImageTesseractScript(): string
    {
        return $this->tesseractScript(<<<'SH'
#!/bin/sh
case "$1" in
    */ocr-*) printf 'Trip Information\nDuty Summary\n' ;;
    *) exit 1 ;;
esac
SH);
    }

    private function emptyTesseractScript(): string
    {
        return $this->tesseractScript("#!/bin/sh\nexit 0\n");
    }

    private function tesseractScript(string $contents): string
    {
        $scriptPath = tempnam(sys_get_temp_dir(), 'tesseract-fail-');

        if ($scriptPath === false) {
            $this->fail('Unable to create temporary OCR test script.');
        }

        file_put_contents($scriptPath, $contents);
        chmod($scriptPath, 0755);
        $this->tesseractScriptPaths[] = $scriptPath;

        return $scriptPath;
    }

    /**
     * @return array<int, string>
     */
    private function ocrTempFiles(): array
    {
        $files = glob(storage_path('app/ocr-*'));

        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }

    protected function tearDown(): void
    {
        foreach ($this->tesseractScriptPaths as $tesseractScriptPath) {
            if (file_exists($tesseractScriptPath)) {
                unlink($tesseractScriptPath);
            }
        }

        parent::tearDown();
    }
}
