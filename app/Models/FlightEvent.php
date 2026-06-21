<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightEvent extends Model
{
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
     * Human friendly duration string (H:MM) calculated from start/end.
     */
    public function getDurationAttribute(): ?string
    {
        if (empty($this->start) || empty($this->end)) {
            return null;
        }

        $start = $this->start instanceof \Illuminate\Support\Carbon ? $this->start : \Illuminate\Support\Carbon::parse($this->start);
        $end = $this->end instanceof \Illuminate\Support\Carbon ? $this->end : \Illuminate\Support\Carbon::parse($this->end);

        $minutes = $start->diffInMinutes($end);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }

}
