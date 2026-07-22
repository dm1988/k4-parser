<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ParseSourceResolutionException extends RuntimeException
{
    /**
     * @param  array<string, string|list<string>>  $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public static function fromThrowable(Throwable $throwable, bool $hasFile): self
    {
        $message = $hasFile
            ? 'Source resolution failed: '
            : 'Roster text resolution failed: ';

        return new self(
            $message.$throwable->getMessage(),
            ['file' => $message.$throwable->getMessage()],
            $throwable,
        );
    }
}
