<?php

namespace App\Actions;

use App\DTOs\ParserResultData;
use App\Exceptions\ParseSourceResolutionException;
use App\Services\Infrastructure\ScheduleRequestLogger;
use Illuminate\Http\UploadedFile;
use Throwable;

class HandleParseExecution
{
    public function __construct(
        private readonly ScheduleRequestLogger $scheduleRequestLogger,
    ) {}

    /**
     * @param  callable(): array{
     *     parsed: array<string, mixed>,
     *     result: ParserResultData,
     *     parser_type?: ?string,
     *     page_count?: ?int
     * }  $operation
     * @return array{
     *     parsed: array<string, mixed>,
     *     result: ParserResultData,
     *     parser_type?: ?string,
     *     page_count?: ?int
     * }
     */
    public function handle(
        ?int $userId,
        string $sourceType,
        string $parserType,
        ?UploadedFile $file,
        callable $operation,
    ): array {
        $startedAt = hrtime(true);
        $parseRequest = $this->scheduleRequestLogger->start($userId, $sourceType, $parserType, $file);

        try {
            $payload = $operation();

            $this->scheduleRequestLogger->success(
                $parseRequest,
                $startedAt,
                $payload['parsed'],
                $payload['parser_type'] ?? null,
                $payload['page_count'] ?? null,
            );

            return $payload;
        } catch (Throwable $throwable) {
            $loggedThrowable = $throwable instanceof ParseSourceResolutionException && $throwable->getPrevious() instanceof Throwable
                ? $throwable->getPrevious()
                : $throwable;

            $this->scheduleRequestLogger->error($parseRequest, $startedAt, $loggedThrowable);

            throw $throwable;
        }
    }
}
