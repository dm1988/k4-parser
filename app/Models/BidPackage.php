<?php

namespace App\Models;

use Database\Factories\BidPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['imported_by', 'period', 'fleet', 'source_revision', 'fingerprint', 'payload', 'published_at', 'archived_at'])]
#[Hidden(['payload', 'fingerprint', 'pay_source'])]
class BidPackage extends Model
{
    /** @use HasFactory<BidPackageFactory> */
    use HasFactory;

    /** @return array{payload: 'encrypted:array', pay_source: 'encrypted:array', published_at: 'immutable_datetime', archived_at: 'immutable_datetime'} */
    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'pay_source' => 'encrypted:array', 'published_at' => 'immutable_datetime', 'archived_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /** @return HasMany<BidWorkspace, $this> */
    public function workspaces(): HasMany
    {
        return $this->hasMany(BidWorkspace::class);
    }
}
