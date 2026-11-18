<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

final readonly class SubmittedBidOrder
{
    /** @param array<string, int> $ranks */
    public function __construct(
        public array $ranks = [],
        public ?CarbonImmutable $checkedAt = null,
        public string $unavailableReason = 'No confirmed submitted order available',
    ) {}

    public function available(): bool
    {
        return $this->checkedAt !== null && $this->ranks !== [];
    }
}
