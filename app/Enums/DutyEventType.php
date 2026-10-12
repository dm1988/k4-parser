<?php

namespace App\Enums;

enum DutyEventType: string
{
    case FlightDuty = 'flight_duty';
    case Deadhead = 'deadhead';
    case Training = 'training';
    case ReserveR1 = 'R1';
    case ReserveR2 = 'R2';
    case ReserveR4 = 'R4';
    case GeneralDuty = 'duty';

    public static function fromValue(?string $value): ?self
    {
        $value = trim((string) $value);

        return self::tryFrom(strtoupper($value)) ?? self::tryFrom(strtolower($value));
    }

    public static function fromEvent(array $event): self
    {
        $metadata = $event['metadata'] ?? $event['meta'] ?? [];
        $explicitType = self::fromValue(data_get($metadata, 'duty_type', $event['dutyType'] ?? $event['duty_type'] ?? null));

        if ($explicitType !== null) {
            return $explicitType;
        }

        $legacyType = strtolower((string) ($event['type'] ?? ''));

        if ($legacyType === 'deadhead' || (bool) data_get($metadata, 'deadhead', $event['isDeadhead'] ?? $event['is_deadhead'] ?? false)) {
            return self::Deadhead;
        }

        if ($legacyType === 'training') {
            return self::Training;
        }

        return self::fromValue(data_get($metadata, 'activity_code', $event['activityCode'] ?? $event['activity_code'] ?? null)) ?? self::GeneralDuty;
    }

    public function label(): string
    {
        return match ($this) {
            self::FlightDuty => 'Flight Duty',
            self::Deadhead => 'Deadhead',
            self::Training => 'Training',
            self::ReserveR1 => 'R1',
            self::ReserveR2 => 'R2',
            self::ReserveR4 => 'R4',
            self::GeneralDuty => 'Duty',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::ReserveR1 => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200',
            self::ReserveR2 => 'bg-indigo-100 text-indigo-900 dark:bg-indigo-900/40 dark:text-indigo-200',
            self::ReserveR4 => 'bg-purple-100 text-purple-900 dark:bg-purple-900/40 dark:text-purple-200',
            self::Training => 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-200',
            self::Deadhead, self::GeneralDuty => 'bg-slate-100 text-slate-900 dark:bg-slate-700 dark:text-slate-200',
            self::FlightDuty => 'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-200',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Deadhead => 'heroicon-o-paper-airplane',
            self::Training => 'heroicon-o-academic-cap',
            default => 'heroicon-o-briefcase',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FlightDuty => 'Duty period associated with an operating flight.',
            self::Deadhead => 'Time spent traveling as a passenger for work purposes.',
            self::Training => 'Scheduled training or recurrent sessions.',
            self::ReserveR1, self::ReserveR2, self::ReserveR4 => $this->label().' reserve assignment.',
            self::GeneralDuty => 'On-duty time without a flight or layover segment.',
        };
    }
}
