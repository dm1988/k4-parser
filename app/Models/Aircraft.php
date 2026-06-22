<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aircraft extends Model
{
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
    public function flightEvents()
    {
        return $this->hasMany(FlightEvent::class, 'tail_number', 'tail_number');
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
