<?php

namespace App\Models;

use Database\Factories\BidSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'bid_workspace_id', 'review', 'status', 'expires_at', 'attempted_at', 'reconciled_at'])]
#[Hidden(['review'])]
class BidSubmission extends Model
{
    /** @use HasFactory<BidSubmissionFactory> */
    use HasFactory;

    use HasUlids;

    /** @return array{review: 'encrypted:array', expires_at: 'immutable_datetime', attempted_at: 'immutable_datetime', reconciled_at: 'immutable_datetime'} */
    protected function casts(): array
    {
        return ['review' => 'encrypted:array', 'expires_at' => 'immutable_datetime', 'attempted_at' => 'immutable_datetime', 'reconciled_at' => 'immutable_datetime'];
    }
}
