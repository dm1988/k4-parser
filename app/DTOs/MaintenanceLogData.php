<?php

namespace App\DTOs;

use App\Enums\EtopsApplicability;
use JsonSerializable;

final readonly class MaintenanceLogData implements JsonSerializable
{
    /**
     * @param  list<MaintenanceItemData>  $items
     */
    public function __construct(
        public bool $sectionPresent,
        public EtopsApplicability $etopsApplicability,
        public array $items = [],
    ) {}

    /** @return array{sectionPresent: bool, etopsApplicability: string, items: list<array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string}>} */
    public function toArray(): array
    {
        return [
            'sectionPresent' => $this->sectionPresent,
            'etopsApplicability' => $this->etopsApplicability->value,
            'items' => array_map(
                static fn (MaintenanceItemData $item): array => $item->toArray(),
                $this->items,
            ),
        ];
    }

    /** @return array{sectionPresent: bool, etopsApplicability: string, items: list<array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string}>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
