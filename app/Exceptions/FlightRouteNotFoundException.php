<?php

namespace App\Exceptions;

use RuntimeException;

class FlightRouteNotFoundException extends RuntimeException
{
    public static function pdfCouldNotBeRead(string $reason): self
    {
        return new self('The uploaded PDF could not be read. '.$reason);
    }

    public static function flightPlanBlockMissing(): self
    {
        return new self('No ICAO flight plan block was found in the uploaded PDF.');
    }

    public static function routeSegmentMissing(): self
    {
        return new self(
            'A flight plan block was found, but the route segment could not be identified between the speed/level and destination lines.'
        );
    }

    public static function routeSegmentEmpty(): self
    {
        return new self('A route section was found, but it was empty after PDF text cleanup.');
    }

    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
