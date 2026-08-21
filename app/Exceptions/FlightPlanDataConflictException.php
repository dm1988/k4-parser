<?php

namespace App\Exceptions;

use Exception;

class FlightPlanDataConflictException extends Exception
{
    public static function forField(string $field): self
    {
        return new self("Conflicting flight release values were found for {$field}.");
    }
}
