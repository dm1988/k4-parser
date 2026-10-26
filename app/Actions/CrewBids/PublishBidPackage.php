<?php

namespace App\Actions\CrewBids;

use App\Models\BidPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PublishBidPackage
{
    public function handle(int $draftId, int $userId): BidPackage
    {
        Gate::authorize('manage-crew-bids');

        return DB::transaction(function () use ($draftId, $userId): BidPackage {
            $draft = BidPackage::query()->where('imported_by', $userId)->lockForUpdate()->findOrFail($draftId);
            if ($draft->published_at === null) {
                $draft->update(['published_at' => now()]);
            }

            return $draft;
        });
    }
}
