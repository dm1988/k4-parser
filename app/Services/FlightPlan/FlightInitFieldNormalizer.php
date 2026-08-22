<?php

namespace App\Services\FlightPlan;

class FlightInitFieldNormalizer
{
    public function acarsInitDate(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== null && preg_match('/^(?:0[1-9]|[12]\d|3[01])$/', $value) === 1
            ? $value
            : null;
    }

    public function employeeNumber(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== null && preg_match('/^\d{4,6}$/', $value) === 1
            ? $value
            : null;
    }
}
