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

    /**
     * Resolves the view model state using the secure cache keys stored in the session.
     */
    public static function fromCurrentSession(array $oldInput = []): self
    {
        $parseKey = session('latest_parse_key');
        $namespace = session('parsed_results_namespace');
        $result = null;

        if (is_string($parseKey) && $parseKey !== '' && is_string($namespace) && $namespace !== '') {
            $result = Cache::get("sessions:{$namespace}:parsed_results:{$parseKey}");
        }

        return self::fromSession($result, $oldInput);
    }

    /**
     * Builds the view model from a resolved cache result array.
     */
    public static function fromSession(mixed $result, array $oldInput = []): self
    {
        // 1. Ensure result filters fall back gracefully to an array if null/missing
        $cachedFilters = is_array($result) && isset($result['filters']) && is_array($result['filters']) 
            ? $result['filters'] 
            : [];

        // 2. Resolve selected event types prioritizing user form input over historical filters
        $selectedTypes = array_values(array_filter(
            is_array($oldInput['event_types'] ?? null) ? $oldInput['event_types'] : $cachedFilters,
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
}