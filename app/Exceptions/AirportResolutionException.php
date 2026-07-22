<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AirportResolutionException extends RuntimeException
{
    public static function missingData(): self
    {
        return new self('A found airport resolution must contain airport data.');
    }

    public static function unexpectedData(): self
    {
        return new self('A non-found airport resolution cannot contain airport data.');
    }

    public static function providerUnavailable(?Throwable $previous = null): self
    {
        return new self('The airport provider is unavailable.', previous: $previous);
    }
}
