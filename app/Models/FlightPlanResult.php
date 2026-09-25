<?php

namespace App\Models;

use Database\Factories\FlightPlanResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'result_key', 'payload'])]
#[Hidden(['payload'])]
class FlightPlanResult extends Model
{
    /** @use HasFactory<FlightPlanResultFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
        ];
    }
}
