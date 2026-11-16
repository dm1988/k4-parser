<?php

namespace App\Models;

use Database\Factories\PilotSeniorityListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @phpstan-type Pilot array{name: string, seniority: int} */
#[Fillable(['source_url', 'effective_date', 'fingerprint', 'imported_by', 'imported_at', 'pilots'])]
#[Hidden(['pilots'])]
class PilotSeniorityList extends Model
{
    /** @use HasFactory<PilotSeniorityListFactory> */
    use HasFactory;

    /** @return array{effective_date: 'immutable_date', imported_at: 'immutable_datetime', pilots: 'encrypted:array'} */
    protected function casts(): array
    {
        return ['effective_date' => 'immutable_date', 'imported_at' => 'immutable_datetime', 'pilots' => 'encrypted:array'];
    }

    public static function current(): ?self
    {
        return self::query()->find(1);
    }
}
