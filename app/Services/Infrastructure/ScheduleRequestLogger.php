<?php

namespace App\Services\Infrastructure;

use App\DTOs\ExtractedEventDTO;
use App\Enums\ScheduleEventType;
use App\Models\ExtractRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ScheduleRequestLogger
{
    public function start(
        ?int $userId,
        string $sourceType,
        string $parserType,
        ?UploadedFile $file = null,
    ): ExtractRequest {
        $path = $file?->getRealPath();

        return ExtractRequest::create([
            'user_id' => $userId,
            'request_uuid' => (string) Str::uuid(),
            'source_type' => $sourceType,
            'parser_type' => $parserType,
            'status' => 'partial',
            'extraction_duration_ms' => 0,
            'file_hash' => is_string($path) && is_file($path) ? hash_file('sha256', $path) : null,
            'file_size_bytes' => $file?->getSize(),
            'detected_event_count' => 0,
            'detected_flight_count' => 0,
            'detected_hotel_count' => 0,
            'app_version' => config('app.version'),
            'extractor_version' => config('app.extractor_version'),
        ]);
    }

    public function success(
        ExtractRequest $extractRequest,
        int $startedAt,
        array $parsed,
        ?string $parserType = null,
        ?int $pageCount = null,
    ): void {
        $counts = $this->eventCounts($parsed['calendar_events'] ?? []);

        $extractRequest->update([
            'parser_type' => $parserType ?? $extractRequest->parser_type,
            'status' => 'success',
            'extraction_duration_ms' => $this->durationMs($startedAt),
            'page_count' => $pageCount,
            ...$counts,
        ]);

        Log::info('K4 extraction completed', [
            'extract_request_id' => $extractRequest->id,
            ...$counts,
        ]);
    }

    public function error(ExtractRequest $extractRequest, int $startedAt, Throwable $e): void
    {
        $extractRequest->update([
            'status' => 'failed',
            'error_code' => class_basename($e),
            'extraction_duration_ms' => $this->durationMs($startedAt),
        ]);

        Log::error('K4 extraction failed', [
            'extract_request_id' => $extractRequest->id,
            'error' => $e->getMessage(),
        ]);
    }

    private function durationMs(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }

    /**
     * @param  iterable<mixed>  $events
     * @return array{detected_event_count: int, detected_flight_count: int, detected_hotel_count: int}
     */
    private function eventCounts(iterable $events): array
    {
        $eventCount = 0;
        $flightCount = 0;
        $hotelCount = 0;

        foreach ($events as $event) {
            $eventCount++;
            $type = $event instanceof ExtractedEventDTO
                ? ScheduleEventType::fromValue($event->type)
                : (is_array($event) ? ScheduleEventType::fromEvent($event) : ScheduleEventType::Unknown);

            if ($type->isFlightLike()) {
                $flightCount++;
            }

            if ($type === ScheduleEventType::Layover) {
                $hotelCount++;
            }
        }

        return [
            'detected_event_count' => $eventCount,
            'detected_flight_count' => $flightCount,
            'detected_hotel_count' => $hotelCount,
        ];
    }
}
