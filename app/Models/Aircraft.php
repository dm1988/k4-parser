<?php

namespace App\Models;

use Database\Factories\AircraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tail_number',
    'manufacturer',
    'type',
    'model',
    'is_active',
    'airline',
])]
class Aircraft extends Model
{
    /** @use HasFactory<AircraftFactory> */
    use HasFactory;

    /**
     * @return HasMany<FlightEvent, $this>
     */
    public function flightEvents(): HasMany
    {
        return $this->hasMany(FlightEvent::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function byAirline(Builder $query, string $airline): void
    {
        $query->where('airline', $airline);
    }

    #[Scope]
    protected function byType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    #[Scope]
    protected function byModel(Builder $query, string $model): void
    {
        $query->where('model', $model);
    }

    #[Scope]
    protected function byTailNumber(Builder $query, string $tailNumber): void
    {
        $query->where('tail_number', $tailNumber);
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->tail_number);
    }
}
