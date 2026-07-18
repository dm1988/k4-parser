<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'iata_code',
    'icao_code',
    'callsign',
    'country',
    'active',
])]
class Airline extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    #[Scope]
    protected function byCode(Builder $query, string $code): void
    {
        $normalizedCode = Str::upper(trim($code));

        $query->where(function (Builder $query) use ($normalizedCode): void {
            $query->where('iata_code', $normalizedCode)
                ->orWhere('icao_code', $normalizedCode);
        });
    }
}
