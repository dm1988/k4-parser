<?php

namespace App\DTOs;

use InvalidArgumentException;
use JsonSerializable;

final readonly class DispatcherNoteData implements JsonSerializable
{
    public string $text;

    public function __construct(string $text)
    {
        $normalized = trim(preg_replace('/\R/u', "\n", $text) ?? $text);

        if ($normalized === '') {
            throw new InvalidArgumentException('Dispatcher note text cannot be empty.');
        }

        $this->text = $normalized;
    }

    public static function fromArray(mixed $value): ?self
    {
        if (! is_array($value) || ! is_string($value['text'] ?? null)) {
            return null;
        }

        try {
            return new self($value['text']);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @return array{text: string} */
    public function toArray(): array
    {
        return ['text' => $this->text];
    }

    /** @return array{text: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
