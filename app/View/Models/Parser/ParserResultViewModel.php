<?php

namespace App\View\Models\Parser;

readonly class ParserResultViewModel
{
    /**
     * @param  list<ParserEventViewModel>  $events
     * @param  list<string>  $filters
     */
    public function __construct(
        public ?string $errorMessage,
        public string $sourceLabel,
        public string $tripNumber,
        public int $eventCount,
        public array $events,
        public ?string $exportUrl,
        public string $rawJson,
    ) {}

    public static function fromArray(array $result): self
    {
        $filters = array_values(array_filter(
            $result['filters'] ?? [],
            fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
        $eventViewModels = [];

        foreach (($result['parsed']['calendar_events'] ?? []) as $index => $event) {
            if (! is_array($event)) {
                continue;
            }

            $eventViewModels[] = ParserEventViewModel::fromArray($event, $index);
        }

        return new self(
            errorMessage: is_string($result['error'] ?? null) ? $result['error'] : null,
            sourceLabel: ucfirst((string) ($result['source'] ?? 'text')),
            tripNumber: (string) ($result['parsed']['trip']['trip_number'] ?? 'Pending'),
            eventCount: count($eventViewModels),
            events: $eventViewModels,
            exportUrl: $eventViewModels === [] ? null : route('parse.export', ['event_types' => $filters]),
            rawJson: json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
        );
    }

    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }
}
