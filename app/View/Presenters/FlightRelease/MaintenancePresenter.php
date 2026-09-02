<?php

namespace App\View\Presenters\FlightRelease;

use App\DTOs\Maintenance\MaintenanceItemData;
use App\Enums\MaintenanceItemType;
use App\View\Models\FlightPlanPageData;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

final readonly class MaintenancePresenter
{
    public function __construct(private ?FlightPlanPageData $pageData) {}

    public function hasSection(): bool
    {
        return $this->pageData?->flightPlan->maintenanceLog?->sectionPresent === true;
    }

    public function rampFuel(): ?string
    {
        $rampFuel = $this->pageData?->flightPlan->fuelPlan?->ramp;

        return $rampFuel === null ? null : Number::format($rampFuel->amount / 1000, precision: 1);
    }

    public function date(): ?string
    {
        return $this->pageData?->flightPlan->identity->flightDate?->format('m d y');
    }

    public function rampFuelLabel(): string
    {
        $unit = $this->pageData?->flightPlan->fuelPlan?->ramp?->unit;

        return $unit === null
            ? 'Estimated ramp fuel'
            : 'Estimated ramp fuel (1,000 '.Str::upper($unit).')';
    }

    public function itemCountLabel(): string
    {
        $count = $this->itemCount();

        return $count.' source-listed '.($count === 1 ? 'item' : 'items');
    }

    public function itemCount(): int
    {
        return count($this->pageData?->flightPlan->maintenanceLog->items ?? []);
    }

    public function typeSummary(): ?string
    {
        $counts = [];

        foreach ($this->pageData?->flightPlan->maintenanceLog->items ?? [] as $item) {
            $counts[$item->type->value] = ($counts[$item->type->value] ?? 0) + 1;
        }

        return $this->countSummary($counts);
    }

    public function statusSummary(): ?string
    {
        $counts = [];

        foreach ($this->pageData?->flightPlan->maintenanceLog->items ?? [] as $item) {
            if ($item->status !== null) {
                $counts[$item->status] = ($counts[$item->status] ?? 0) + 1;
            }
        }

        return $this->countSummary($counts);
    }

    /**
     * @return list<array{type: string, typeTitle: string, typeDescription: string, typeBadgeColor: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string, copyable: bool}>
     */
    public function items(): array
    {
        return array_map(static fn (MaintenanceItemData $item): array => [
            'type' => $item->type->value,
            'typeTitle' => $item->type->title(),
            'typeDescription' => $item->type->description(),
            'typeBadgeColor' => $item->type->badgeColor(),
            'number' => $item->number,
            'description' => $item->description,
            'reference' => $item->reference,
            'status' => $item->status,
            'limitations' => $item->limitations,
            'procedures' => $item->procedures,
            'copyable' => in_array($item->type, [MaintenanceItemType::Mel, MaintenanceItemType::Cdl, MaintenanceItemType::Nef], true),
        ], $this->pageData?->flightPlan->maintenanceLog->items ?? []);
    }

    /** @param array<string, int> $counts */
    private function countSummary(array $counts): ?string
    {
        if ($counts === []) {
            return null;
        }

        return implode(' · ', array_map(
            static fn (string $label, int $count): string => $count.' '.Str::upper($label),
            array_keys($counts),
            array_values($counts),
        ));
    }
}
