<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aircraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'tail_number',
        'manufacturer',
        'type',
        'model',
        'is_active',
        'airline',
    ];
    protected $casts = [
        'is_active' => 'boolean',
    ];
    /**
     * @return HasMany<FlightEvent, self>
     */
    public function flightEvents(): HasMany
    {
        return $this->hasMany(FlightEvent::class, 'aircraft_id');
    }
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    public function scopeByAirline($query, $airline)
    {
        return $query->where('airline', $airline);
    }
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
    public function scopeByModel($query, $model)
    {
        return $query->where('model', $model);
    }
    public function scopeByTailNumber($query, $tailNumber)
    {
        return $query->where('tail_number', $tailNumber);
    }
    public function getDisplayNameAttribute()
    {
        if ($this->aircraft_name) {
            return $this->aircraft_name . " ({$this->tail_number})";
        }
        return $this->tail_number;
    }
}
