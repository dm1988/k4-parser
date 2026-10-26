<?php

namespace App\DTOs;

use App\Enums\ReleaseStatus;
use JsonSerializable;

final readonly class ReleaseStatusData implements JsonSerializable
{
    public function __construct(
        public ReleaseStatus $status = ReleaseStatus::Unknown,
        public ?string $sourceWarning = null,
    ) {}

    public static function fromArray(mixed $value): self
    {
        if (! is_array($value) || ! is_string($value['status'] ?? null)) {
            return new self;
        }

        $status = ReleaseStatus::tryFrom($value['status']);
        $warning = $value['sourceWarning'] ?? null;

        if ($status === ReleaseStatus::Preliminary) {
            return is_string($warning) && trim($warning) !== ''
                ? new self($status, $warning)
                : new self;
        }

        return $status === ReleaseStatus::NotMarkedPreliminary && $warning === null
            ? new self($status)
            : new self;
    }

    /** @return array{status: string, sourceWarning: ?string} */
    public function toArray(): array
    {
        return ['status' => $this->status->value, 'sourceWarning' => $this->sourceWarning];
    }

    /** @return array{status: string, sourceWarning: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return array{preliminary: bool, label: ?string, warning: ?string} */
    public function display(): array
    {
        return match ($this->status) {
            ReleaseStatus::Preliminary => [
                'preliminary' => true,
                'label' => 'Preliminary flight plan',
                'warning' => 'Payload estimated — Do not fuel aircraft',
            ],
            ReleaseStatus::Unknown => [
                'preliminary' => false,
                'label' => 'Release status not determined',
                'warning' => 'Re-upload the flight plan to determine its release status.',
            ],
            ReleaseStatus::NotMarkedPreliminary => [
                'preliminary' => false,
                'label' => null,
                'warning' => null,
            ],
        };
    }
}
