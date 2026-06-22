<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Airline extends Model
{
    protected $fillable = [
        'name',
        'iata_code',
        'icao_code',
        'callsign',
        'country',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeByCode(Builder $query, string $code): Builder
    {
        $code = strtoupper(trim($code));

        return $query->where('iata_code', $code)
            ->orWhere('icao_code', $code);
    }
}
