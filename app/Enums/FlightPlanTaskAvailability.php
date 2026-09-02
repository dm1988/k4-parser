<?php

namespace App\Enums;

enum FlightPlanTaskAvailability: string
{
    case Available = 'available';
    case NotPresent = 'not_present';
    case NotSupported = 'not_supported';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::NotPresent => 'Not present',
            self::NotSupported => 'Not supported',
        };
    }

    public function tone(bool $absenceIsGood = false): TaskTone
    {
        return match ($this) {
            self::Available => $absenceIsGood ? TaskTone::Warning : TaskTone::Neutral,
            self::NotPresent => $absenceIsGood ? TaskTone::Success : TaskTone::Danger,
            self::NotSupported => TaskTone::Warning,
        };
    }

    public function badgeColor(bool $absenceIsGood = false): string
    {
        return $this->tone($absenceIsGood)->badgeColor();
    }

    public function dotColor(bool $absenceIsGood = false): string
    {
        return $this->tone($absenceIsGood)->dotColor();
    }
}
