<?php

namespace App\Actions\Extract;

use App\DTOs\ExtractedEventDTO;
use App\DTOs\ExtractedResultData;
use App\Enums\ScheduleEventType;
use App\Exceptions\ExtractSourceResolutionException;
use App\Services\Infrastructure\ExtractRequestLogger;
use Illuminate\Http\UploadedFile;
use Throwable;

class HandleExtractExecution
{
    public function __construct(
        private readonly ExtractRequestLogger $extractRequestLogger,
    ) {}

    /**
     * @param  callable(): array{
     *     parsed: array<string, mixed>,
     *     result: ExtractedResultData,
     *     parser_type?: ?string,
     *     page_count?: ?int
     * }  $operation
     * @return array{
     *     parsed: array<string, mixed>,
     *     result: ExtractedResultData,
     *     parser_type?: ?string,
     *     page_count?: ?int
     * }
     */
    public function handle(
        ?int $userId,
        string $sourceType,
        string $parserType,
        array|UploadedFile|null $file,
        callable $operation,
    ): array {
        $startedAt = hrtime(true);
        $extractRequest = $this->extractRequestLogger->start($userId, $sourceType, $parserType, $file);

        try {
            $payload = $operation();

            $counts = $this->eventCounts($payload['parsed']['calendar_events'] ?? []);

            $this->extractRequestLogger->complete(
                $extractRequest,
                $startedAt,
                $counts['detected_event_count'],
                $counts['detected_flight_count'],
                $counts['detected_hotel_count'],
                $payload['parser_type'] ?? null,
                $payload['page_count'] ?? null,
            );

            return $payload;
        } catch (Throwable $throwable) {
            $loggedThrowable = $throwable instanceof ExtractSourceResolutionException && $throwable->getPrevious() instanceof Throwable
                ? $throwable->getPrevious()
                : $throwable;

            $this->extractRequestLogger->error($extractRequest, $startedAt, $loggedThrowable);

            throw $throwable;
        }
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
