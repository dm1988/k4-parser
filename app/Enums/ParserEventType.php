<?php

namespace App\Enums;

enum ParserEventType: string
{
    case Flight = 'flight';
    case Duty = 'duty';
    case Layover = 'layover';
    case Unknown = 'unknown';

    public static function fromValue(?string $value): self
    {
        return self::tryFrom(strtolower((string) $value)) ?? self::Unknown;
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
            self::Unknown => 'Unknown',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Flight => 'bg-blue-100 text-blue-900',
            self::Duty => 'bg-green-100 text-green-900',
            self::Layover => 'bg-gray-100 text-gray-900',
            self::Unknown => 'bg-[#C5A059]/20 text-[#1B365D]',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Flight => 'heroicon-o-paper-airplane',
            self::Duty => 'heroicon-o-briefcase',
            self::Layover => 'heroicon-o-building-office-2',
            self::Unknown => 'heroicon-o-question-mark-circle',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Flight => 'Scheduled flying segment.',
            self::Duty => 'On-duty time without a flight or layover segment.',
            self::Layover => 'Ground time between duty periods, typically with hotel details.',
            self::Unknown => 'Event type could not be classified from the parsed data.',
        };
    }

    public function filterLabel(): string
    {
        return match ($this) {
            self::Flight => 'Flights only',
            self::Duty => 'Duties only',
            self::Layover => 'Layovers only',
            self::Unknown => 'Unknown only',
        };
    }

    public function isFilterable(): bool
    {
        return $this !== self::Unknown;
    }
}
