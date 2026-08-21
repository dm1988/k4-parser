<?php

namespace App\DTOs;

use App\Enums\MaintenanceItemType;
use JsonSerializable;

final readonly class MaintenanceItemData implements JsonSerializable
{
    public function __construct(
        public MaintenanceItemType $type,
        public string $number,
        public string $description,
        public ?string $reference = null,
        public ?string $status = null,
        public ?string $limitations = null,
        public ?string $procedures = null,
    ) {}

    /** @return array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'number' => $this->number,
            'description' => $this->description,
            'reference' => $this->reference,
            'status' => $this->status,
            'limitations' => $this->limitations,
            'procedures' => $this->procedures,
        ];
    }

    /** @return array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
