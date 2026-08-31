<?php

namespace App\DTOs;

use App\Enums\OperationsSpecification;
use JsonSerializable;

final readonly class ReleaseAuthorizationData implements JsonSerializable
{
    public function __construct(
        public OperationsSpecification $operationsSpecification = OperationsSpecification::Unknown,
    ) {}

    /** @return array{operationsSpecification: string} */
    public function toArray(): array
    {
        return ['operationsSpecification' => $this->operationsSpecification->value];
    }

    /** @return array{operationsSpecification: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
