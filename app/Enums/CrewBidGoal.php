<?php

namespace App\Enums;

enum CrewBidGoal: string
{
    case Credit = 'credit';
    case DaysOff = 'days_off';
    case Blocks = 'blocks';
    case Pay = 'pay';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Maximize credit',
            self::DaysOff => 'Maximize days off',
            self::Blocks => 'Fewer work blocks',
            self::Pay => 'Maximize pay',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $goal) {
            $options[$goal->value] = $goal->label();
        }

        return $options;
    }
}
