<?php

namespace App\DTOs;

use JsonSerializable;

final readonly class ParserResultData implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $parsed
     * @param  list<string>  $filters
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $type = '',
        public string $source = 'text',
        public ?string $documentType = null,
        public mixed $file = null,
        public ?string $mime = null,
        public array $parsed = [],
        public array $filters = [],
        public array $meta = [],
        public ?string $parseKey = null,
        public ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: is_string($data['type'] ?? null) ? $data['type'] : '',
            source: is_string($data['source'] ?? null) ? $data['source'] : 'text',
            documentType: is_string($data['document_type'] ?? null) ? $data['document_type'] : null,
            file: $data['file'] ?? null,
            mime: is_string($data['mime'] ?? null) ? $data['mime'] : null,
            parsed: is_array($data['parsed'] ?? null) ? $data['parsed'] : [],
            filters: array_values(array_filter(
                is_array($data['filters'] ?? null) ? $data['filters'] : [],
                static fn (mixed $filter): bool => is_string($filter),
            )),
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
            parseKey: is_string($data['parse_key'] ?? null) ? $data['parse_key'] : null,
            error: is_string($data['error'] ?? null) ? $data['error'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'source' => $this->source,
            'document_type' => $this->documentType,
            'file' => $this->file,
            'mime' => $this->mime,
            'parsed' => $this->parsed,
            'filters' => $this->filters,
            'meta' => $this->meta,
            'parse_key' => $this->parseKey,
            ...($this->error === null ? [] : ['error' => $this->error]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
