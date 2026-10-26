<?php

namespace App\Models;

use Database\Factories\BidPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['imported_by', 'period', 'fleet', 'source_revision', 'fingerprint', 'payload', 'published_at'])]
#[Hidden(['payload', 'fingerprint'])]
class BidPackage extends Model
{
    /** @use HasFactory<BidPackageFactory> */
    use HasFactory;

    /** @return array{payload: 'encrypted:array', published_at: 'immutable_datetime'} */
    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'published_at' => 'immutable_datetime'];
    }
}
