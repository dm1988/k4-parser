<?php

namespace App\Actions;

use App\DTOs\ExtractedResultData;
use App\Exceptions\ExtractSourceResolutionException;
use App\Services\Infrastructure\ScheduleRequestLogger;
use Illuminate\Http\UploadedFile;
use Throwable;

class HandleExtractExecution
{
    public function __construct(
        private readonly ScheduleRequestLogger $scheduleRequestLogger,
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
        $extractRequest = $this->scheduleRequestLogger->start($userId, $sourceType, $parserType, $file);

        try {
            $payload = $operation();

            $this->scheduleRequestLogger->success(
                $extractRequest,
                $startedAt,
                $payload['parsed'],
                $payload['parser_type'] ?? null,
                $payload['page_count'] ?? null,
            );

            return $payload;
        } catch (Throwable $throwable) {
            $loggedThrowable = $throwable instanceof ExtractSourceResolutionException && $throwable->getPrevious() instanceof Throwable
                ? $throwable->getPrevious()
                : $throwable;

            $this->scheduleRequestLogger->error($extractRequest, $startedAt, $loggedThrowable);

            throw $throwable;
        }
    }
}
