<?php

namespace App\Models;

use Database\Factories\SubmittedBidSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'user_id', 'bid_package_id', 'seat', 'evidence', 'started_at', 'checked_at'])]
#[Hidden(['evidence'])]
class SubmittedBidSnapshot extends Model
{
    /** @use HasFactory<SubmittedBidSnapshotFactory> */
    use HasFactory;

    use HasUlids;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @return array{evidence: 'encrypted:array', started_at: 'immutable_datetime', checked_at: 'immutable_datetime'} */
    protected function casts(): array
    {
        return ['evidence' => 'encrypted:array', 'started_at' => 'immutable_datetime', 'checked_at' => 'immutable_datetime'];
    }
}
