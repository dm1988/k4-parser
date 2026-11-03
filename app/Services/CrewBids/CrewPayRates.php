<?php

namespace App\Services\CrewBids;

use Carbon\CarbonImmutable;

class CrewPayRates
{
    /** @var array<string, mixed>|null */
    private ?array $table = null;

    /** @var array<string, array{step: int, cents: int}|null> */
    private array $resolved = [];

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->table()['_meta'];
    }

    /** @return array{step: int, cents: int}|null */
    public function resolve(string $seat, string $date, string $longevity, bool $checkAirman = false): ?array
    {
        $key = $seat.'|'.$date.'|'.$longevity.'|'.(int) $checkAirman;
        if (! array_key_exists($key, $this->resolved)) {
            $this->resolved[$key] = $this->resolveRate($seat, $date, $longevity, $checkAirman);
        }

        return $this->resolved[$key];
    }

    /** @return array{step: int, cents: int}|null */
    private function resolveRate(string $seat, string $date, string $longevity, bool $checkAirman): ?array
    {
        if (! in_array($seat, ['CA', 'FO'], true) || ! CarbonImmutable::hasFormat($date, 'Y-m-d')
            || ! CarbonImmutable::hasFormat($longevity, 'Y-m-d') || $date < $longevity || $date < $this->metadata()['effective_from']) {
            return null;
        }
        $start = CarbonImmutable::parse($longevity, 'UTC');
        $day = CarbonImmutable::parse($date, 'UTC');
        $anniversary = sprintf('%04d-%s', $day->year, $start->format('m-d'));
        if ($start->format('m-d') === '02-29' && ! $day->isLeapYear()) {
            $anniversary = sprintf('%04d-03-01', $day->year);
        }
        $step = min(12, max(1, $day->year - $start->year + ($date >= $anniversary ? 1 : 0)));
        $decimal = number_format($this->table()[$seat][$step - 1], 2, '.', '');

        return ['step' => $step, 'cents' => (int) str_replace('.', '', $decimal) + ($checkAirman ? 1500 : 0)];
    }

    /** @return array<string, mixed> */
    private function table(): array
    {
        return $this->table ??= json_decode(file_get_contents(__DIR__.'/../../Data/pay_rates.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
