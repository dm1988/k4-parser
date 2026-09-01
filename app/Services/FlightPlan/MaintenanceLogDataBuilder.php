<?php

namespace App\Services\FlightPlan;

use App\DTOs\Maintenance\MaintenanceInputData;
use App\DTOs\Maintenance\MaintenanceItemData;
use App\DTOs\Maintenance\MaintenanceLogData;
use App\Enums\MaintenanceItemType;
use Illuminate\Support\Str;

class MaintenanceLogDataBuilder
{
    public function fromExtracted(MaintenanceInputData $source): MaintenanceLogData
    {
        return new MaintenanceLogData(
            sectionPresent: $source->sectionPresent,
            items: $this->items($source->items),
        );
    }

    public function fromSerialized(mixed $source): ?MaintenanceLogData
    {
        return is_array($source) ? $this->build($source, 'sectionPresent') : null;
    }

    /** @param array<string, mixed> $source */
    private function build(array $source, string $sectionPresentKey): MaintenanceLogData
    {
        return new MaintenanceLogData(
            sectionPresent: ($source[$sectionPresentKey] ?? false) === true,
            items: $this->items($source['items'] ?? null),
        );
    }

    /** @return list<MaintenanceItemData> */
    private function items(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = is_string($item['type'] ?? null)
                ? MaintenanceItemType::tryFrom(Str::upper($item['type']))
                : null;
            $number = $this->nullableString($item['number'] ?? null);
            $description = $this->nullableString($item['description'] ?? null);

            if ($type === null || $number === null || $description === null) {
                continue;
            }

            $normalized[] = new MaintenanceItemData(
                type: $type,
                number: Str::upper($number),
                description: $description,
                reference: $this->nullableString($item['reference'] ?? null),
                status: $this->nullableString($item['status'] ?? null),
                limitations: $this->nullableString($item['limitations'] ?? null),
                procedures: $this->nullableString($item['procedures'] ?? null),
            );
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}
