<?php

namespace App\Models;

use Database\Factories\BidAwardReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bid_package_id', 'imported_by', 'period', 'fleet', 'fingerprint', 'source', 'awards'])]
#[Hidden(['awards'])]
class BidAwardReport extends Model
{
    /** @use HasFactory<BidAwardReportFactory> */
    use HasFactory;

    /** @return array{awards: 'encrypted:array'} */
    protected function casts(): array
    {
        return ['awards' => 'encrypted:array'];
    }

    /** @return BelongsTo<BidPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(BidPackage::class, 'bid_package_id');
    }

    /** @return Builder<BidAwardReport> */
    public static function availableTo(User $user): Builder
    {
        return self::query()->whereHas('package', fn (Builder $query) => $query->whereNotNull('published_at')
            ->where(fn (Builder $query) => $query->whereNull('archived_at')->orWhereHas('workspaces', fn (Builder $query) => $query->where('user_id', $user->id))));
    }
}
