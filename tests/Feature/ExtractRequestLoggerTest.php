<?php

namespace Tests\Feature;

use App\Models\ExtractRequest;
use App\Models\User;
use App\Services\Infrastructure\ExtractRequestLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ExtractRequestLoggerTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('uploadInputs')]
    public function test_uploaded_file_count_is_persisted_at_start_and_retained_on_completion(string $inputType, int $expectedCount): void
    {
        $user = User::factory()->create();
        $image = UploadedFile::fake()->image('roster.png');
        $file = match ($inputType) {
            'text' => null,
            'empty' => [],
            'image' => $image,
            'pdf' => UploadedFile::fake()->create('roster.pdf', 120, 'application/pdf'),
            'one image array' => [$image],
            'two images' => [$image, UploadedFile::fake()->image('second.png')],
            'five identical images' => array_fill(0, 5, $image),
            'non-upload entries' => [$image, null, 'not an upload'],
        };
        $logger = app(ExtractRequestLogger::class);
        $request = $logger->start($user->getKey(), $expectedCount === 0 ? 'pasted_text' : 'image', 'roster', $file);

        $storedRequest = ExtractRequest::query()->sole();
        $this->assertSame($expectedCount, $storedRequest->uploaded_file_count);
        $this->assertSame($user->getKey(), $storedRequest->user_id);

        $logger->complete($request, hrtime(true), 0, 0, 0);

        $this->assertSame($expectedCount, ExtractRequest::query()->sole()->uploaded_file_count);
        if ($inputType === 'five identical images') {
            $this->assertSame(5 * $image->getSize(), $request->fresh()->file_size_bytes);
            $this->assertSame(hash('sha256', str_repeat(hash_file('sha256', $image->getRealPath()), 5)), $request->file_hash);
        }
    }

    /** @return array<string, array{string, int}> */
    public static function uploadInputs(): array
    {
        return [
            'pasted text' => ['text', 0],
            'empty file list' => ['empty', 0],
            'single image' => ['image', 1],
            'single PDF' => ['pdf', 1],
            'single-item list' => ['one image array', 1],
            'multiple images' => ['two images', 2],
            'duplicates at schedule limit' => ['five identical images', 5],
            'only file objects count' => ['non-upload entries', 1],
        ];
    }

    public function test_failed_extraction_retains_the_original_upload_count(): void
    {
        Log::spy();
        $logger = app(ExtractRequestLogger::class);
        $request = $logger->start(null, 'image', 'roster', [
            UploadedFile::fake()->image('first.png'),
            UploadedFile::fake()->image('second.png'),
        ]);

        $logger->error($request, hrtime(true), new RuntimeException('Parser failed'));

        $storedRequest = ExtractRequest::query()->sole();
        $this->assertSame(2, $storedRequest->uploaded_file_count);
        $this->assertSame('failed', $storedRequest->status);
    }

    public function test_count_does_not_depend_on_a_readable_hash_path(): void
    {
        $file = new class(__FILE__, 'roster.pdf', 'application/pdf', null, true) extends UploadedFile
        {
            public function getRealPath(): false
            {
                return false;
            }
        };

        $request = app(ExtractRequestLogger::class)->start(null, 'pdf', 'roster', $file);

        $this->assertSame(1, $request->fresh()->uploaded_file_count);
        $this->assertNull($request->file_hash);
    }

    public function test_successful_extraction_is_recorded_without_an_info_log(): void
    {
        $logger = app(ExtractRequestLogger::class);
        $extractRequest = $logger->start(null, 'pdf', 'flight_plan');
        $log = Log::spy();

        $logger->complete(
            $extractRequest,
            hrtime(true),
            detectedEventCount: 3,
            detectedFlightCount: 2,
            detectedHotelCount: 1,
            parserType: 'flight_release',
            pageCount: 12,
        );

        $extractRequest->refresh();

        $this->assertSame('success', $extractRequest->status);
        $this->assertSame('flight_release', $extractRequest->parser_type);
        $this->assertSame(12, $extractRequest->page_count);
        $this->assertSame(3, $extractRequest->detected_event_count);
        $this->assertSame(2, $extractRequest->detected_flight_count);
        $this->assertSame(1, $extractRequest->detected_hotel_count);
        $log->shouldNotHaveReceived(
            'info',
            fn (string $message, array $context): bool => $message === 'K4 extraction completed',
        );
    }
}
