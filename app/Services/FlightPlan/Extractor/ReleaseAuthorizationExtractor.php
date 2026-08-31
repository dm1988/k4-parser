<?php

namespace App\Services\FlightPlan\Extractor;

use App\Enums\OperationsSpecification;
use App\Exceptions\FlightPlanDataConflictException;
use Illuminate\Support\Str;

class ReleaseAuthorizationExtractor
{
    /**
     * @return array{
     *     data: array{operations_specification: string},
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $matches = [];
        preg_match_all(
            '/(?<![A-Z0-9])RELEASED\s*IAW\s*OPS\s*SPEC\s*B0(43|44)(?![A-Z0-9])/i',
            $text,
            $matches,
        );

        $specifications = array_values(array_unique($matches[1]));

        if (count($specifications) > 1) {
            throw FlightPlanDataConflictException::forField('Operations Specification');
        }

        $operationsSpecification = match ($specifications[0] ?? null) {
            '43' => OperationsSpecification::B43,
            '44' => OperationsSpecification::B44,
            default => OperationsSpecification::Unknown,
        };

        return [
            'data' => ['operations_specification' => $operationsSpecification->value],
            'source_fragments' => $operationsSpecification === OperationsSpecification::Unknown
                ? []
                : ['release_authorization' => Str::squish($matches[0][0])],
        ];
    }
}
