<?php

namespace App\Enums;

enum TaskTone: string
{
    case Success = 'success';
    case Danger = 'danger';
    case Warning = 'warning';
    case Neutral = 'neutral';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Success => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-400/15 dark:text-emerald-200',
            self::Danger => 'bg-red-100 text-red-900 dark:bg-red-400/15 dark:text-red-200',
            self::Warning => 'bg-amber-100 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200',
            self::Neutral => 'bg-[#1B365D]/10 text-[#1B365D] dark:bg-blue-400/15 dark:text-blue-200',
        };
    }

    public function dotColor(): string
    {
        return match ($this) {
            self::Success => 'bg-emerald-500 dark:bg-emerald-400',
            self::Danger => 'bg-red-500 dark:bg-red-400',
            self::Warning => 'bg-amber-400 dark:bg-amber-300',
            self::Neutral => 'bg-[#1B365D] dark:bg-blue-300',
        };
    }
}
