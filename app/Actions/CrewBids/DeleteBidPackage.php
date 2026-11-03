<?php

namespace App\Actions\CrewBids;

use App\Models\BidPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteBidPackage
{
    public function handle(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $package = BidPackage::query()->lockForUpdate()->findOrFail($id);
            Gate::authorize('delete', $package);

            return (bool) $package->delete();
        });
    }
}
