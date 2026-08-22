<?php

namespace App\DTOs;

use JsonSerializable;

final readonly class FlightInitData implements JsonSerializable
{
    public function __construct(
        public bool $sectionPresent,
        public ?string $acarsInitDate = null,
    ) {}

    /** @return array{sectionPresent: bool, acarsInitDate: ?string} */
    public function toArray(): array
    {
        return [
            'sectionPresent' => $this->sectionPresent,
            'acarsInitDate' => $this->acarsInitDate,
        ];
    }

    /** @return array{sectionPresent: bool, acarsInitDate: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
