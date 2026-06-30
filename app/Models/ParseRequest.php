<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'request_uuid',
    'source_type',
    'parser_type',
    'status',
    'error_code',
    'parse_duration_ms',
    'file_hash',
    'file_size_bytes',
    'page_count',
    'detected_event_count',
    'detected_flight_count',
    'detected_hotel_count',
    'app_version',
    'parser_version',
])]
class ParseRequest extends Model
{
    public const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
