<?php

namespace App\Services;

use App\DTOs\ParsedEventDTO;
use App\Enums\ParserEventType;
use App\Models\ParseRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ParseRequestLogger
{
    public function start(
        ?int $userId,
        string $sourceType,
        string $parserType,
        ?UploadedFile $file = null,
    ): ParseRequest {
        $path = $file?->getRealPath();

        return ParseRequest::create([
            'user_id' => $userId,
            'request_uuid' => (string) Str::uuid(),
            'source_type' => $sourceType,
            'parser_type' => $parserType,
            'status' => 'partial',
            'parse_duration_ms' => 0,
            'file_hash' => is_string($path) && is_file($path) ? hash_file('sha256', $path) : null,
            'file_size_bytes' => $file?->getSize(),
            'detected_event_count' => 0,
            'detected_flight_count' => 0,
            'detected_hotel_count' => 0,
            'app_version' => config('app.version'),
            'parser_version' => config('app.parser_version'),
        ]);
    }

    public function success(
        ParseRequest $parseRequest,
        int $startedAt,
        array $parsed,
        ?string $parserType = null,
        ?int $pageCount = null,
    ): void {
        $counts = $this->eventCounts($parsed['calendar_events'] ?? []);

        $parseRequest->update([
            'parser_type' => $parserType ?? $parseRequest->parser_type,
            'status' => 'success',
            'parse_duration_ms' => $this->durationMs($startedAt),
            'page_count' => $pageCount,
            ...$counts,
        ]);

        Log::info('K4 parse completed', [
            'parse_request_id' => $parseRequest->id,
            ...$counts,
        ]);
    }

    public function error(ParseRequest $parseRequest, int $startedAt, Throwable $e): void
    {
        $parseRequest->update([
            'status' => 'failed',
            'error_code' => class_basename($e),
            'parse_duration_ms' => $this->durationMs($startedAt),
        ]);

        Log::error('K4 parse failed', [
            'parse_request_id' => $parseRequest->id,
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
            $type = $event instanceof ParsedEventDTO
                ? ParserEventType::fromValue($event->type)
                : (is_array($event) ? ParserEventType::fromEvent($event) : ParserEventType::Unknown);

            if ($type->isFlightLike()) {
                $flightCount++;
            }

            if ($type === ParserEventType::Layover) {
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
