<?php

namespace App\Enums;

enum ScheduleEventType: string
{
    case Flight = 'flight';
    case Duty = 'duty';
    case Layover = 'layover';
    case Off = 'off';
    case OneInSeven = '1in7';
    case Unknown = 'unknown';

    public static function fromValue(?string $value): self
    {
        return match (strtolower((string) $value)) {
            'deadhead', 'training' => self::Duty,
            default => self::tryFrom(strtolower((string) $value)) ?? self::Unknown,
        };
    }

    public static function fromEvent(array $event): self
    {
        $type = self::fromValue((string) ($event['type'] ?? null));
        if ($type === self::Flight && DutyEventType::fromEvent($event) === DutyEventType::Deadhead) {
            return self::Duty;
        }

        return $type;
    }

    /**
     * @return list<string>
     */
    public static function filterValues(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            array_filter(self::cases(), static fn (self $type): bool => $type->isFilterable()),
        );
    }

    /**
     * @return list<self>
     */
    public static function filterable(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $type): bool => $type->isFilterable(),
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::Flight => 'Flight',
            self::Duty => 'Duty',
            self::Layover => 'Layover',
            self::Off => 'Off',
            self::OneInSeven => '1-in-7',
            self::Unknown => 'Unknown',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Flight => 'bg-blue-100 text-blue-900',
            self::Duty => 'bg-green-100 text-green-900',
            self::Layover => 'bg-gray-100 text-gray-900',
            self::Off => 'bg-purple-100 text-purple-900',
            self::OneInSeven => 'bg-pink-100 text-pink-900',
            self::Unknown => 'bg-[#C5A059]/20 text-[#1B365D]',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Flight => 'heroicon-o-paper-airplane',
            self::Duty => 'heroicon-o-briefcase',
            self::Layover => 'heroicon-o-building-office-2',
            self::Off => 'heroicon-o-bed-double',
            self::OneInSeven => 'heroicon-o-calendar',
            self::Unknown => 'heroicon-o-question-mark-circle',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Flight => 'Scheduled flying segment.',
            self::Duty => 'Operational duties, including reserve, training, and deadhead assignments.',
            self::Layover => 'Ground time between duty periods, typically with hotel details.',
            self::Off => 'Scheduled time off from work.',
            self::OneInSeven => 'Indicates a 1-in-7 day off requirement',
            self::Unknown => 'Event type could not be classified from the parsed data.',
        };
    }

    public function filterLabel(): string
    {
        return match ($this) {
            self::Flight => 'Flights only',
            self::Duty => 'Duties only',
            self::Layover => 'Layovers only',
            self::Off => 'Off days only',
            self::OneInSeven => '1-in-7 only',
            self::Unknown => 'Unknown only',
        };
    }

    public function isFilterable(): bool
    {
        return in_array($this, [self::Flight, self::Duty], true);
    }

    public function isFlightLike(): bool
    {
        return $this === self::Flight;
    }

    public static function isFlightEvent(array $event): bool
    {
        $type = self::fromEvent($event);

        if ($type === self::Flight) {
            return true;
        }

        return $type === self::Duty
            && DutyEventType::fromEvent($event) === DutyEventType::Deadhead
            && filled(data_get($event, 'metadata.origin', $event['origin'] ?? null))
            && filled(data_get($event, 'metadata.destination', $event['destination'] ?? null));
    }
}
