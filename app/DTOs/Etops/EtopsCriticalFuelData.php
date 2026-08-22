<?php

namespace App\DTOs\Etops;

use App\ValueObjects\FuelQuantity;
use Illuminate\Support\Str;
use JsonSerializable;

final readonly class EtopsCriticalFuelData implements JsonSerializable
{
    public ?string $basis;

    public function __construct(
        public FuelQuantity $quantity,
        ?string $basis = null,
    ) {
        $basis = $basis === null ? null : Str::squish($basis);
        $this->basis = $basis === '' ? null : $basis;
    }

    /** @return array{quantity: array{amount: float, unit: 'kg'|'lb'}, basis: ?string} */
    public function toArray(): array
    {
        return ['quantity' => $this->quantity->toArray(), 'basis' => $this->basis];
    }

    /** @return array{quantity: array{amount: float, unit: 'kg'|'lb'}, basis: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
