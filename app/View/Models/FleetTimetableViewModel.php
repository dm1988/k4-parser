<?php

namespace App\View\Models;

use App\Models\Aircraft;
use App\Models\FlightEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

readonly class FleetTimetableViewModel
{
    private const WINDOW_HOURS = 30;

    /**
     * @param  list<array{label: string, left: float, is_day_boundary: bool}>  $ticks
     * @param  list<array{tail_number: string, description: string, flight_aware_url: string, events: list<array<string, mixed>>}>  $rows
     * @param  array<string, string>  $statusLegend
     */
    public function __construct(
        public CarbonImmutable $now,
        public CarbonImmutable $windowStart,
        public CarbonImmutable $windowEnd,
        public array $ticks,
        public array $rows,
        public array $statusLegend,
    ) {}

    public static function make(?CarbonImmutable $now = null): self
    {
        $now = ($now ?? CarbonImmutable::now('UTC'))->utc()->startOfMinute();
        $windowStart = $now->subHours(15);
        $windowEnd = $now->addHours(15);

        $aircraft = Aircraft::query()
            ->active()
            ->with(['flightEvents' => fn ($query) => $query
                ->where('start', '<', $windowEnd)
                ->where('end', '>', $windowStart)
                ->orderBy('start')])
            ->orderBy('tail_number')
            ->get();

        return new self(
            now: $now,
            windowStart: $windowStart,
            windowEnd: $windowEnd,
            ticks: self::ticks($windowStart),
            rows: $aircraft->map(fn (Aircraft $item): array => self::row($item, $windowStart, $windowEnd))->all(),
            statusLegend: [
                'Scheduled' => 'bg-slate-500 border-slate-300',
                'En Route' => 'bg-sky-700 border-sky-300',
                'Arrived' => 'bg-emerald-700 border-emerald-300',
                'Cancelled' => 'bg-rose-700 border-rose-300',
                'Other' => 'bg-amber-700 border-amber-300',
            ],
        );
    }

    /** @return list<array{label: string, left: float, is_day_boundary: bool}> */
    private static function ticks(CarbonImmutable $windowStart): array
    {
        return Collection::times(self::WINDOW_HOURS + 1)
            ->map(function (int $index) use ($windowStart): array {
                $time = $windowStart->addHours($index - 1);

                return [
                    'label' => $time->format($time->hour === 0 ? 'M j · H:i' : 'H:i'),
                    'left' => (($index - 1) / self::WINDOW_HOURS) * 100,
                    'is_day_boundary' => $time->hour === 0,
                ];
            })->all();
    }

    /** @return array{tail_number: string, description: string, flight_aware_url: string, events: list<array<string, mixed>>} */
    private static function row(Aircraft $aircraft, CarbonImmutable $windowStart, CarbonImmutable $windowEnd): array
    {
        return [
            'tail_number' => $aircraft->tail_number,
            'description' => $aircraft->model ?: $aircraft->type ?: 'Aircraft',
            'flight_aware_url' => 'https://www.flightaware.com/live/flight/'.rawurlencode($aircraft->tail_number),
            'events' => $aircraft->flightEvents
                ->map(fn (FlightEvent $event): array => self::event($event, $windowStart, $windowEnd))
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function event(FlightEvent $event, CarbonImmutable $windowStart, CarbonImmutable $windowEnd): array
    {
        $start = CarbonImmutable::instance($event->start)->utc();
        $end = CarbonImmutable::instance($event->end)->utc();
        $visibleStart = $start->max($windowStart);
        $visibleEnd = $end->min($windowEnd);
        $windowMinutes = self::WINDOW_HOURS * 60;
        $status = trim((string) $event->status) ?: 'Other';

        return [
            'flight_number' => $event->flight_number ?: $event->title,
            'route' => ($event->origin ?: '---').' → '.($event->destination ?: '---'),
            'status' => $status,
            'status_classes' => self::statusClasses($status),
            'left' => ($windowStart->diffInMinutes($visibleStart) / $windowMinutes) * 100,
            'width' => max(($visibleStart->diffInMinutes($visibleEnd) / $windowMinutes) * 100, 0.35),
            'starts_before_window' => $start->lessThan($windowStart),
            'ends_after_window' => $end->greaterThan($windowEnd),
            'time_label' => $start->format('M j H:i').'–'.$end->format('M j H:i').' UTC',
        ];
    }

    private static function statusClasses(string $status): string
    {
        return match (strtolower($status)) {
            'scheduled' => 'bg-slate-500 border-slate-300',
            'en route', 'enroute' => 'bg-sky-700 border-sky-300',
            'arrived', 'completed' => 'bg-emerald-700 border-emerald-300',
            'cancelled', 'canceled' => 'bg-rose-700 border-rose-300',
            default => 'bg-amber-700 border-amber-300',
        };
    }
}
