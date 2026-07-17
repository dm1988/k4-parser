<?php

namespace App\Actions;

use App\Exceptions\ParseSourceResolutionException;
use App\Services\ParseRequestLogger;
use Illuminate\Http\UploadedFile;
use Throwable;

class HandleParseExecution
{
    public function __construct(
        private readonly ParseRequestLogger $parseRequestLogger,
    ) {}

    /**
     * @param  callable(): array{
     *     parsed: array<string, mixed>,
     *     result: array<string, mixed>,
     *     parser_type?: ?string,
     *     page_count?: ?int
     * }  $operation
     * @return array{
     *     parsed: array<string, mixed>,
     *     result: array<string, mixed>,
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
        $parseRequest = $this->parseRequestLogger->start($userId, $sourceType, $parserType, $file);

        try {
            $payload = $operation();

            $this->parseRequestLogger->success(
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

            $this->parseRequestLogger->error($parseRequest, $startedAt, $loggedThrowable);

            throw $throwable;
        }
    }
}
