<?php

namespace App\View\Models\Parser;

use App\Enums\ParserEventType;
use Illuminate\Support\Facades\Cache;

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
    ) {}

    public static function fromSession(mixed $result, array $oldInput = []): self
    {
        $result = self::resolveResult($result);

        $selectedTypes = array_values(array_filter(
            is_array($oldInput['event_types'] ?? null) ? $oldInput['event_types'] : ($result['filters'] ?? []),
            fn (mixed $value): bool => is_string($value) && in_array($value, ParserEventType::filterValues(), true),
        ));

        return new self(
            result: is_array($result) ? ParserResultViewModel::fromArray($result) : null,
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
        );
    }

    public function hasResult(): bool
    {
        return $this->result !== null;
    }

    private static function resolveResult(mixed $result): ?array
    {
        if (! is_array($result)) {
            return null;
        }

        if (isset($result['parsed']['calendar_events'])) {
            return $result;
        }

        $parseKey = is_string($result['parse_key'] ?? null) ? $result['parse_key'] : null;

        if ($parseKey !== null) {
            $cachedResult = Cache::get("parsed_results:{$parseKey}");

            if (is_array($cachedResult) && isset($cachedResult['parsed']['calendar_events'])) {
                return $cachedResult;
            }
        }

        if (is_array($result['parsed'] ?? null)) {
            return [
                ...$result,
                'parsed' => [
                    'trip' => [],
                    'calendar_events' => array_values(array_filter(
                        $result['parsed'],
                        static fn (mixed $event): bool => is_array($event),
                    )),
                ],
            ];
        }

        return $result;
    }
}
