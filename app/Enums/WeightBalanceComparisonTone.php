<?php

namespace App\Enums;

enum WeightBalanceComparisonTone: string
{
    case Safe = 'safe';
    case Heavy = 'heavy';
    case Caution = 'caution';
    case Exceeded = 'exceeded';

    public function label(): string
    {
        return match ($this) {
            self::Safe => 'Within operating margin',
            self::Heavy => 'Heavy operation',
            self::Caution => 'Near structural limit',
            self::Exceeded => 'Structural limit exceeded',
        };
    }

    public function textClasses(): string
    {
        return match ($this) {
            self::Safe => 'text-emerald-700 dark:text-emerald-300',
            self::Heavy => 'text-[#1B365D] dark:text-sky-300',
            self::Caution => 'text-amber-700 dark:text-amber-300',
            self::Exceeded => 'text-red-700 dark:text-red-300',
        };
    }

    public function progressClass(): string
    {
        return match ($this) {
            self::Safe => 'cc-weight-progress-safe',
            self::Heavy => 'cc-weight-progress-heavy',
            self::Caution => 'cc-weight-progress-caution',
            self::Exceeded => 'cc-weight-progress-exceeded',
        };
    }
}
