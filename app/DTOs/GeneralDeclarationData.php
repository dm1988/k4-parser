<?php

namespace App\DTOs;

use JsonSerializable;

final readonly class GeneralDeclarationData implements JsonSerializable
{
    public function __construct(
        public bool $sectionPresent,
    ) {}

    /** @return array{sectionPresent: bool} */
    public function toArray(): array
    {
        return ['sectionPresent' => $this->sectionPresent];
    }

    /** @return array{sectionPresent: bool} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
