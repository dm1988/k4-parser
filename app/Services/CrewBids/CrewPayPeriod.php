<?php

namespace App\Services\CrewBids;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CrewPayPeriod
{
    public CarbonImmutable $start;

    public CarbonImmutable $end;

    /** @var list<string> */
    private array $dates;

    public function __construct(public string $label)
    {
        if (! preg_match('/\A\d{4}-(?:0[1-9]|1[0-2])\z/', $label)) {
            throw new InvalidArgumentException('Invalid compensation period.');
        }
        $month = CarbonImmutable::parse($label.'-01', 'UTC');
        $this->start = match ($month->month) {
            2 => $month->subDay(),
            3 => $month->addDay(),
            default => $month,
        };
        $this->end = match ($month->month) {
            1 => $month->addDays(29),
            2 => $month->addMonth(),
            default => $month->endOfMonth()->startOfDay(),
        };
        $dates = [];
        for ($date = $this->start; $date <= $this->end; $date = $date->addDay()) {
            $dates[] = $date->format('Y-m-d');
        }
        $this->dates = $dates;
    }

    /** @return list<string> */
    public function dates(): array
    {
        return $this->dates;
    }

    public function contains(string $date): bool
    {
        return $date >= $this->start->format('Y-m-d') && $date <= $this->end->format('Y-m-d');
    }

    public static function labelForDate(string $date): string
    {
        $day = CarbonImmutable::parse($date, 'UTC');

        return match ($day->format('m-d')) {
            '01-31' => $day->addMonthNoOverflow()->format('Y-m'),
            '03-01' => $day->subMonthNoOverflow()->format('Y-m'),
            default => $day->format('Y-m'),
        };
    }
}
