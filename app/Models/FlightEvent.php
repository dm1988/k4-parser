<?php

namespace App\Models;

use Database\Factories\FlightEventFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'title',
    'type',
    'start',
    'end',
    'timezone',
    'metadata',
    'type_label',
    'type_description',
    'type_icon',
    'schedule_label',
    'duration_label',
    'tail_number',
    'origin',
    'destination',
    'is_deadhead',
    'badge_color',
    'download_url',
    'download_id',
    'trip_id',
    'flight_number',
    'status',
    'aircraft_id',
])]
class FlightEvent extends Model
{
    /** @use HasFactory<FlightEventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Aircraft, $this>
     */
    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'end' => 'datetime',
            'is_deadhead' => 'boolean',
            'metadata' => 'array',
        ];
    }

    #[Scope]
    protected function byTailNumber(Builder $query, string $tailNumber): void
    {
        $query->where('tail_number', $tailNumber);
    }

    #[Scope]
    protected function byOrigin(Builder $query, string $origin): void
    {
        $query->where('origin', $origin);
    }

    #[Scope]
    protected function byDestination(Builder $query, string $destination): void
    {
        $query->where('destination', $destination);
    }

    #[Scope]
    protected function byDateRange(
        Builder $query,
        DateTimeInterface|string $startDate,
        DateTimeInterface|string $endDate,
    ): void {
        $query->whereBetween('start', [$startDate, $endDate]);
    }

    #[Scope]
    protected function byTimeRange(
        Builder $query,
        DateTimeInterface|string $startTime,
        DateTimeInterface|string $endTime,
    ): void {
        $query->whereBetween('start', [$startTime, $endTime]);
    }

    protected function duration(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->start === null || $this->end === null) {
                return null;
            }

            $minutes = (int) $this->start->diffInMinutes($this->end);

            return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
        });
    }

    protected function displayTailNumber(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->aircraft?->tail_number ?? $this->tail_number);
    }
}
