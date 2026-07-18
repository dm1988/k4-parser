<?php

namespace App\DTOs;

use ArrayAccess;
use JsonSerializable;

abstract readonly class ParsedEventDTO implements ArrayAccess, JsonSerializable
{
    public function __construct(
        public string $title,
        public string $type,
        public string $typeLabel,
        public string $typeDescription,
        public string $typeIcon,
        public string $scheduleLabel,
        public string $durationLabel,
        public bool $isDeadhead,
        public string $badgeColor,
        public string $downloadUrl,
        public ?string $downloadId = null,
        public ?string $start = null,
        public ?string $end = null,
        public ?string $timezone = null,
        public array $metadata = [],
    ) {}

    abstract public function toArray(): array;

    abstract public function withDownloadId(string $downloadId): static;

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[(string) $offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        unset($offset, $value);

        throw new \LogicException(static::class.' is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($offset);

        throw new \LogicException(static::class.' is immutable.');
    }

    public function __get(string $name): mixed
    {
        return $this->toArray()[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseArray(): array
    {
        return [
            'title' => $this->title,
            'type' => $this->type,
            'typeLabel' => $this->typeLabel,
            'typeDescription' => $this->typeDescription,
            'typeIcon' => $this->typeIcon,
            'scheduleLabel' => $this->scheduleLabel,
            'durationLabel' => $this->durationLabel,
            'isDeadhead' => $this->isDeadhead,
            'badgeColor' => $this->badgeColor,
            'downloadUrl' => $this->downloadUrl,
            'downloadId' => $this->downloadId,
            'start' => $this->start,
            'end' => $this->end,
            'timezone' => $this->timezone,
            'metadata' => $this->metadata,
            'download_id' => $this->downloadId,
            'is_deadhead' => $this->isDeadhead,
            'badge_color' => $this->badgeColor,
            'download_url' => $this->downloadUrl,
            'meta' => $this->metadata,
        ];
    }

    protected static function metadataFrom(array $data): array
    {
        $metadata = $data['metadata'] ?? $data['meta'] ?? [];

        return is_array($metadata) ? $metadata : [];
    }

    protected static function stringOrDefault(array $data, string $primaryKey, ?string $secondaryKey = null, string $default = ''): string
    {
        $value = $data[$primaryKey] ?? ($secondaryKey !== null ? ($data[$secondaryKey] ?? null) : null);

        if ($value === null) {
            return $default;
        }

        $value = trim((string) $value);

        return $value === '' ? $default : $value;
    }

    protected static function nullableString(array $data, string $primaryKey, ?string $secondaryKey = null): ?string
    {
        $value = $data[$primaryKey] ?? ($secondaryKey !== null ? ($data[$secondaryKey] ?? null) : null);

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected static function nullableInt(array $data, string $primaryKey, ?string $secondaryKey = null): ?int
    {
        $value = $data[$primaryKey] ?? ($secondaryKey !== null ? ($data[$secondaryKey] ?? null) : null);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected static function boolOrDefault(array $data, string $primaryKey, ?string $secondaryKey = null, bool $default = false): bool
    {
        $value = $data[$primaryKey] ?? ($secondaryKey !== null ? ($data[$secondaryKey] ?? null) : null);

        return $value === null ? $default : (bool) $value;
    }

    /**
     * @return list<string>
     */
    protected static function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values($normalized);
    }
}
