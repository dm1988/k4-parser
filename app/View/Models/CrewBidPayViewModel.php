<?php

namespace App\View\Models;

use App\DTOs\CrewBidPayEstimate;
use Brick\Math\BigRational;

final readonly class CrewBidPayViewModel
{
    /**
     * @param  list<array{label: string, amount: string, basis: string}>  $components
     * @param  list<string>  $missing
     * @param  list<string>  $assumptions
     * @param  list<string>  $rates
     * @param  list<array{label: string, amount: string}>  $allocations
     */
    public function __construct(
        public bool $ready,
        public string $label,
        public string $period,
        public array $components,
        public array $missing,
        public array $assumptions,
        public array $rates,
        public string $version,
        public bool $loaded = true,
        public string $title = 'Estimated pay',
        public array $allocations = [],
    ) {}

    public static function fromEstimate(CrewBidPayEstimate $estimate, bool $details = true): self
    {
        $label = $estimate->cents === null ? match ($estimate->status) {
            'unconfigured' => 'Set up pay',
            'unsupported' => 'Unavailable',
            default => 'Inputs needed',
        } : self::money($estimate->cents);
        if ($estimate->cents === null && $estimate->minimumCents !== null) {
            $label = self::money($estimate->minimumCents).' minimum';
        }

        return new self(
            ready: $estimate->status === 'ready',
            label: $label,
            period: $estimate->period,
            components: array_map(static fn (array $component): array => [
                'label' => $component['label'],
                'amount' => self::money(CrewBidPayEstimate::roundedCents(BigRational::of($component['exact_cents']))),
                'basis' => $component['basis'].($component['rate_cents'] === null ? '' : '; '.self::money($component['rate_cents']).'/h'),
            ], $details ? $estimate->components : []),
            missing: $estimate->missing,
            assumptions: $details ? $estimate->assumptions : [],
            rates: array_map(static fn (array $rate): string => $rate['start'].'–'.$rate['end'].' UTC: '.$rate['seat'].' step '.$rate['step'].' · '.self::money($rate['cents']).'/h', $details ? $estimate->rates : []),
            version: $estimate->version,
            loaded: $details,
            title: $estimate->includesCarry ? 'Estimated line value' : 'Estimated pay',
            allocations: array_map(static fn (array $allocation): array => [
                'label' => $allocation['period'].' · '.$allocation['label'],
                'amount' => self::money(CrewBidPayEstimate::roundedCents(BigRational::of($allocation['exact_cents']))),
            ], $details ? $estimate->allocations : []),
        );
    }

    public static function money(int $cents): string
    {
        return '$'.number_format(intdiv($cents, 100)).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
