<?php

namespace App\DTOs;

use App\Enums\SlotDirection;
use App\ValueObjects\AirportCode;
use Carbon\CarbonImmutable;
use JsonSerializable;
use Throwable;

final readonly class SlotTimeData implements JsonSerializable
{
    public function __construct(
        public SlotDirection $direction,
        public AirportCode $airport,
        public CarbonImmutable $instantUtc,
        public string $sourceTime,
        public ?int $toleranceMinutes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $direction = SlotDirection::tryFrom((string) ($data['direction'] ?? ''));
        $airport = trim((string) ($data['airport'] ?? ''));
        $instant = trim((string) ($data['instantUtc'] ?? $data['instant_utc'] ?? ''));
        $sourceTime = trim((string) ($data['sourceTime'] ?? $data['source_time'] ?? ''));
        $toleranceMinutes = filter_var(
            $data['toleranceMinutes'] ?? $data['tolerance_minutes'] ?? null,
            FILTER_VALIDATE_INT,
            FILTER_NULL_ON_FAILURE,
        );

        if ($direction === null || $airport === '' || $instant === '' || $sourceTime === '') {
            return null;
        }

        try {
            return new self(
                $direction,
                new AirportCode($airport),
                CarbonImmutable::parse($instant)->utc(),
                $sourceTime,
                is_int($toleranceMinutes) && $toleranceMinutes >= 0 ? $toleranceMinutes : null,
            );
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{direction: string, airport: string, instantUtc: string, sourceTime: string, toleranceMinutes: ?int} */
    public function toArray(): array
    {
        return [
            'direction' => $this->direction->value,
            'airport' => $this->airport->value,
            'instantUtc' => $this->instantUtc->toIso8601String(),
            'sourceTime' => $this->sourceTime,
            'toleranceMinutes' => $this->toleranceMinutes,
        ];
    }

    /** @return array{direction: string, airport: string, instantUtc: string, sourceTime: string, toleranceMinutes: ?int} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
