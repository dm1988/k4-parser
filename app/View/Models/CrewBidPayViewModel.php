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
    ) {}

    public static function fromEstimate(CrewBidPayEstimate $estimate): self
    {
        $label = $estimate->cents === null ? match ($estimate->status) {
            'unconfigured' => 'Set up pay',
            'unsupported' => 'Unavailable',
            default => 'Inputs needed',
        } : self::money($estimate->cents);

        return new self(
            ready: $estimate->status === 'ready',
            label: $label,
            period: $estimate->period,
            components: array_map(static fn (array $component): array => [
                'label' => $component['label'],
                'amount' => self::money(CrewBidPayEstimate::roundedCents(BigRational::of($component['exact_cents']))),
                'basis' => $component['basis'].($component['rate_cents'] === null ? '' : '; '.self::money($component['rate_cents']).'/h'),
            ], $estimate->components),
            missing: $estimate->missing,
            assumptions: $estimate->assumptions,
            rates: array_map(static fn (array $rate): string => $rate['start'].'–'.$rate['end'].' UTC: '.$rate['seat'].' step '.$rate['step'].' · '.self::money($rate['cents']).'/h', $estimate->rates),
            version: $estimate->version,
        );
    }

    public static function money(int $cents): string
    {
        return '$'.number_format(intdiv($cents, 100)).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
