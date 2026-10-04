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
            self::Safe => 'text-emerald-700 dark:text-emerald-400',
            self::Heavy => 'text-sky-700 dark:text-sky-400',
            self::Caution => 'text-amber-700 dark:text-amber-400',
            self::Exceeded => 'text-rose-700 dark:text-rose-400',
        };
    }

    public function overviewCardClasses(): string
    {
        return match ($this) {
            self::Safe => 'border-slate-200 border-l-4 border-l-emerald-600 bg-emerald-500/5 dark:border-slate-700 dark:border-l-emerald-400 dark:bg-emerald-400/10',
            self::Heavy => 'border-slate-200 border-l-4 border-l-sky-600 bg-sky-500/5 dark:border-slate-700 dark:border-l-sky-400 dark:bg-sky-400/10',
            self::Caution => 'border-slate-200 border-l-4 border-l-amber-700 bg-amber-500/5 dark:border-slate-700 dark:border-l-amber-400 dark:bg-amber-400/10',
            self::Exceeded => 'border-slate-200 border-l-4 border-l-rose-600 bg-rose-500/5 dark:border-slate-700 dark:border-l-rose-400 dark:bg-rose-400/10',
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
            self::Safe => 'border border-emerald-600/20 bg-emerald-100 text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/15 dark:text-emerald-300',
            self::Heavy => 'border border-sky-600/20 bg-sky-100 text-sky-700 dark:border-sky-400/20 dark:bg-sky-400/15 dark:text-sky-300',
            self::Caution => 'border border-amber-700/20 bg-amber-100 text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/15 dark:text-amber-300',
            self::Exceeded => 'border border-rose-600/20 bg-rose-100 text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/15 dark:text-rose-300',
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
