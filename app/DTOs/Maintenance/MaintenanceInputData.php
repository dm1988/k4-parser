<?php

namespace App\DTOs\Maintenance;

final readonly class MaintenanceInputData
{
    /**
     * @param  list<array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string}>  $items
     */
    public function __construct(
        public bool $sectionPresent,
        public array $items,
    ) {}

    /** @param array<string, mixed> $source */
    public static function fromExtracted(array $source): self
    {
        return new self(
            sectionPresent: ($source['section_present'] ?? false) === true,
            items: is_array($source['items'] ?? null) ? $source['items'] : [],
        );
    }
}
