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

    public function overviewCardClasses(): string
    {
        return match ($this) {
            self::Safe => 'border-emerald-500/30 border-l-4 border-l-emerald-500 bg-emerald-500/5 backdrop-blur dark:border-emerald-400/30 dark:border-l-emerald-400 dark:bg-emerald-400/10',
            self::Heavy => 'border-[#1B365D]/30 border-l-4 border-l-[#1B365D] bg-[#1B365D]/5 backdrop-blur dark:border-sky-400/30 dark:border-l-sky-400 dark:bg-sky-400/10',
            self::Caution => 'border-amber-500/30 border-l-4 border-l-amber-500 bg-amber-500/5 backdrop-blur dark:border-amber-400/30 dark:border-l-amber-400 dark:bg-amber-400/10',
            self::Exceeded => 'border-red-500/30 border-l-4 border-l-red-500 bg-red-500/5 backdrop-blur dark:border-red-400/30 dark:border-l-red-400 dark:bg-red-400/10',
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

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Safe => 'cc-weight-badge cc-weight-badge-safe text-white',
            self::Heavy => 'cc-weight-badge cc-weight-badge-heavy text-white',
            self::Caution => 'cc-weight-badge cc-weight-badge-caution text-[#0B0E14]',
            self::Exceeded => 'cc-weight-badge cc-weight-badge-exceeded text-white',
        };
    }

    public function isOperationalAlert(): bool
    {
        return $this !== self::Safe;
    }

    public function severity(): int
    {
        return match ($this) {
            self::Safe => 0,
            self::Heavy => 1,
            self::Caution => 2,
            self::Exceeded => 3,
        };
    }
}
