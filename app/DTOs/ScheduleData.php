<?php

namespace App\DTOs;

final readonly class ScheduleData
{
    public function __construct(
        public ?string $etdUtc = null,
        public ?string $etdLocal = null,
        public ?string $etaUtc = null,
        public ?string $etaLocal = null,
        public ?string $blockDuration = null,
        public ?string $reportTimeUtc = null,
        public ?string $reportTimeLocal = null,
        public ?string $dutyEndUtc = null,
        public ?string $dutyEndLocal = null,
        public array $slotTimesUtc = [],
        public array $slotTimesLocal = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            etdUtc: self::nullableString($data, 'etdUtc', 'etd_utc', 'start', 'flight_utc_start'),
            etdLocal: self::nullableString($data, 'etdLocal', 'etd_local', 'legLocalStart', 'leg_local_start'),
            etaUtc: self::nullableString($data, 'etaUtc', 'eta_utc', 'end', 'flight_utc_end'),
            etaLocal: self::nullableString($data, 'etaLocal', 'eta_local', 'legLocalEnd', 'leg_local_end'),
            blockDuration: self::nullableString($data, 'blockDuration', 'block_duration', 'blockTime', 'block_time'),
            reportTimeUtc: self::nullableString($data, 'reportTimeUtc', 'report_time_utc', 'dutyUtcStart', 'duty_utc_start'),
            reportTimeLocal: self::nullableString($data, 'reportTimeLocal', 'report_time_local', 'dutyLocalStart', 'duty_local_start'),
            dutyEndUtc: self::nullableString($data, 'dutyEndUtc', 'duty_end_utc', 'dutyUtcEnd', 'duty_utc_end'),
            dutyEndLocal: self::nullableString($data, 'dutyEndLocal', 'duty_end_local', 'dutyLocalEnd', 'duty_local_end'),
            slotTimesUtc: self::stringList($data['slotTimesUtc'] ?? $data['slot_times_utc'] ?? []),
            slotTimesLocal: self::stringList($data['slotTimesLocal'] ?? $data['slot_times_local'] ?? []),
        );
    }

    /**
     * @return array{etdUtc: string|null, etdLocal: string|null, etaUtc: string|null, etaLocal: string|null, blockDuration: string|null, reportTimeUtc: string|null, reportTimeLocal: string|null, dutyEndUtc: string|null, dutyEndLocal: string|null, slotTimesUtc: list<string|null>, slotTimesLocal: list<string>}
     */
    public function toArray(): array
    {
        return [
            'etdUtc' => $this->etdUtc,
            'etdLocal' => $this->etdLocal,
            'etaUtc' => $this->etaUtc,
            'etaLocal' => $this->etaLocal,
            'blockDuration' => $this->blockDuration,
            'reportTimeUtc' => $this->reportTimeUtc,
            'reportTimeLocal' => $this->reportTimeLocal,
            'dutyEndUtc' => $this->dutyEndUtc,
            'dutyEndLocal' => $this->dutyEndLocal,
            'slotTimesUtc' => $this->slotTimesUtc,
            'slotTimesLocal' => $this->slotTimesLocal,
        ];
    }

    private static function nullableString(array $data, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                continue;
            }

            $value = trim((string) $data[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function stringList(mixed $values): array
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

        return $normalized;
    }
}
