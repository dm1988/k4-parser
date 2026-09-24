<?php

namespace Tests\Feature;

use App\Services\Infrastructure\ExtractRequestLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ExtractRequestLoggerTest extends TestCase
{
    use RefreshDatabase;

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
