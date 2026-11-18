<?php

namespace App\DTOs;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

final readonly class CrewBidPayEstimate
{
    /**
     * @param  list<array{label: string, exact_cents: string, basis: string, rate_cents: ?int}>  $components
     * @param  list<string>  $missing
     * @param  list<string>  $assumptions
     * @param  list<array{start: string, end: string, step: int, cents: int, seat: string}>  $rates
     * @param  list<array{period: string, label: string, exact_cents: string}>  $allocations
     */
    public function __construct(
        public string $status,
        public ?int $cents = null,
        public array $components = [],
        public array $missing = [],
        public array $assumptions = [],
        public array $rates = [],
        public string $period = '',
        public string $version = 'scheduled-pay-2026-11-03-carry',
        public ?int $minimumCents = null,
        public array $allocations = [],
        public bool $includesCarry = false,
    ) {}

    public static function roundedCents(BigRational $amount): int
    {
        return $amount->toScale(0, RoundingMode::HalfUp)->toInt();
    }
}
