<?php

namespace App\View\Models\Parser;

use App\DTOs\ParserResultData;
use App\Enums\ParserEventType;

readonly class ParserPageViewModel
{
    /**
     * @param  list<string>  $selectedTypes
     * @param  list<array{value: string, label: string, description: string}>  $filterOptions
     */
    public function __construct(
        public ?ParserResultViewModel $result,
        public array $selectedTypes,
        public array $filterOptions,
        public string $text,
        public bool $available,
    ) {}

    public static function fromResult(?ParserResultData $result, array $oldInput = []): self
    {
        $selectedTypes = array_values(array_filter(
            is_array($oldInput['event_types'] ?? null) ? $oldInput['event_types'] : ($result === null ? [] : $result->filters),
            fn (mixed $value): bool => is_string($value) && in_array($value, ParserEventType::filterValues(), true),
        ));

        return new self(
            result: $result === null ? null : ParserResultViewModel::fromData($result),
            selectedTypes: $selectedTypes,
            filterOptions: array_map(
                static fn (ParserEventType $type): array => [
                    'value' => $type->value,
                    'label' => $type->filterLabel(),
                    'description' => $type->description(),
                ],
                ParserEventType::filterable(),
            ),
            text: is_string($oldInput['text'] ?? null) ? $oldInput['text'] : '',
            available: auth()->user()?->canUseScheduleParser() ?? false,
        );
    }

    public function hasResult(): bool
    {
        return $this->result !== null;
    }
}
