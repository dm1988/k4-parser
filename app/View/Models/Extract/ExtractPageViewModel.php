<?php

namespace App\View\Models\Extract;

use App\DTOs\ExtractedResultData;
use App\Enums\ScheduleEventType;

readonly class ExtractPageViewModel
{
    /**
     * @param  list<string>  $selectedTypes
     * @param  list<array{value: string, label: string, description: string}>  $filterOptions
     */
    public function __construct(
        public ?ExtractResultViewModel $result,
        public array $selectedTypes,
        public array $filterOptions,
        public bool $available,
    ) {}

    public static function fromResult(?ExtractedResultData $result): self
    {
        $selectedTypes = array_values(array_filter(
            $result === null ? [] : $result->filters,
            fn (string $value): bool => in_array($value, ScheduleEventType::filterValues(), true),
        ));

        return new self(
            result: $result === null ? null : ExtractResultViewModel::fromData($result),
            selectedTypes: $selectedTypes,
            filterOptions: array_map(
                static fn (ScheduleEventType $type): array => [
                    'value' => $type->value,
                    'label' => $type->filterLabel(),
                    'description' => $type->description(),
                ],
                ScheduleEventType::filterable(),
            ),
            available: auth()->user()?->canUseScheduleExtractor() ?? false,
        );
    }

    public function hasResult(): bool
    {
        return $this->result !== null;
    }
}
