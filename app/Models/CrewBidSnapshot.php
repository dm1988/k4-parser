<?php

namespace App\Models;

use Database\Factories\CrewBidSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['period', 'fleet', 'seat', 'portal_package_id', 'portal_revision', 'retrieved_at', 'bidders'])]
#[Hidden(['bidders'])]
class CrewBidSnapshot extends Model
{
    /** @use HasFactory<CrewBidSnapshotFactory> */
    use HasFactory;

    /** @return array{bidders: 'encrypted:array', retrieved_at: 'immutable_datetime', portal_package_id: 'integer', portal_revision: 'integer'} */
    protected function casts(): array
    {
        return ['bidders' => 'encrypted:array', 'retrieved_at' => 'immutable_datetime', 'portal_package_id' => 'integer', 'portal_revision' => 'integer'];
    }

    public static function forPackage(BidPackage $package, string $seat): ?self
    {
        return self::query()->where('period', $package->period)->where('fleet', $package->fleet)->where('seat', $seat)->first();
    }

    public function stale(): bool
    {
        return $this->retrieved_at->lt(now()->subMinutes(15));
    }
}
