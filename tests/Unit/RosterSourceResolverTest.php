<?php

namespace Tests\Unit;

use App\Services\RosterSourceResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RosterSourceResolverTest extends TestCase
{
    private ?string $tesseractScriptPath = null;

    public function test_it_cleans_up_generated_ocr_temp_files_when_ocr_fails(): void
    {
        config()->set('services.ocr.tesseract_path', $this->failingTesseractScript());

        $before = $this->ocrTempFiles();
        $file = UploadedFile::fake()->image('roster.png', 300, 200);

        try {
            app(RosterSourceResolver::class)->resolve($file, null);
            $this->fail('Expected OCR failure was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['OCR failed. Try a sharper roster screenshot or paste the extracted text instead.'],
                $exception->errors()['image'] ?? [],
            );
        }

        $this->assertSame($before, $this->ocrTempFiles());
    }

    private function failingTesseractScript(): string
    {
        $scriptPath = tempnam(sys_get_temp_dir(), 'tesseract-fail-');

        if ($scriptPath === false) {
            $this->fail('Unable to create temporary OCR test script.');
        }

        file_put_contents($scriptPath, "#!/bin/sh\nexit 1\n");
        chmod($scriptPath, 0755);
        $this->tesseractScriptPath = $scriptPath;

        return $scriptPath;
    }

    /**
     * @return array<int, string>
     */
    private function ocrTempFiles(): array
    {
        $files = glob(storage_path('app/ocr_temp_*.jpg'));

        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }

    protected function tearDown(): void
    {
        if (is_string($this->tesseractScriptPath) && file_exists($this->tesseractScriptPath)) {
            unlink($this->tesseractScriptPath);
        }

        parent::tearDown();
    }
}
