<?php

namespace App\Services\CrewBids;

use SensitiveParameter;

class ReadPersonalSubmittedBids
{
    public function __construct(private SubmitPortalBid $portal) {}

    /** @return array{context: array<string, mixed>, numbers: list<int>} */
    public function read(#[SensitiveParameter] string $employee, #[SensitiveParameter] string $password): array
    {
        return $this->portal->readSubmitted($employee, $password);
    }
}
