<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FlightEvent extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes.
     *
     * @var string[]
     */
    protected $fillable = [
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
        'source',
        'external_id',
    ];

    /**
     * Attribute casting.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'is_deadhead' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Aircraft, self>
     */
    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class, 'aircraft_id');
    }

    public function scopeByTailNumber(Builder $query, string $tailNumber): Builder
    {
        return $query->where('tail_number', $tailNumber);
    }

    public function scopeByOrigin(Builder $query, string $origin): Builder
    {
        return $query->where('origin', $origin);
    }

    public function scopeByDestination(Builder $query, string $destination): Builder
    {
        return $query->where('destination', $destination);
    }

    /**
     * @param  DateTimeInterface|string  $startDate
     * @param  DateTimeInterface|string  $endDate
     */
    public function scopeByDateRange(
        Builder $query,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate
    ): Builder {
        return $query->whereBetween('start', [$startDate, $endDate]);
    }

    /**
     * Use this when filtering by a specific time range, not just dates. This will include events that start or end within the range.
     */
    public function scopeByTimeRange(
        Builder $query,
        DateTimeInterface|string $startTime,
        DateTimeInterface|string $endTime
    ): Builder {
        return $query->whereBetween('start', [$startTime, $endTime]);
    }

    /**
     * Human friendly duration string (H:MM) calculated from start/end.
     */
    public function getDurationAttribute(): ?string
    {
        if (empty($this->start) || empty($this->end)) {
            return null;
        }

        $start = $this->start instanceof Carbon ? $this->start : Carbon::parse($this->start);
        $end = $this->end instanceof Carbon ? $this->end : Carbon::parse($this->end);

        $minutes = $start->diffInMinutes($end);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }
}
