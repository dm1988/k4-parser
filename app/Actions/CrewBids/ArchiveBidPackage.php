<?php

namespace App\Actions\CrewBids;

use App\Models\BidPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchiveBidPackage
{
    public function handle(int $id, bool $archive): BidPackage
    {
        return DB::transaction(function () use ($id, $archive): BidPackage {
            $package = BidPackage::query()->lockForUpdate()->findOrFail($id);
            Gate::authorize($archive ? 'archive' : 'restore', $package);
            $package->update(['archived_at' => $archive ? now() : null]);

            return $package;
        });
    }
}
