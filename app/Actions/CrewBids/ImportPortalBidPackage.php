<?php

namespace App\Actions\CrewBids;

use App\Models\BidPackage;
use App\Models\User;
use App\Services\CrewBids\DownloadPortalBidPackage;
use App\Services\CrewBids\ParseBidDocuments;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ImportPortalBidPackage
{
    public function __construct(private DownloadPortalBidPackage $downloader, private ParseBidDocuments $parser) {}

    /** @return 'missing'|'unchanged'|'published'|'revision_held' */
    public function handle(string $period, string $fleet, User $owner): string
    {
        Gate::forUser($owner)->authorize('manage-crew-bids');

        return Cache::lock('crew-bids:portal-import:'.$period.':'.$fleet, 300)->block(5, function () use ($period, $fleet, $owner): string {
            $files = $this->downloader->download($period, $fleet);
            if ($files === null) {
                return 'missing';
            }

            try {
                $data = $this->parser->parse($files);
                if ($data['period'] !== $period || $data['fleet'] !== $fleet
                    || $data['payload']['coverage'] !== ['overview' => true, 'lines' => true, 'trips' => true]
                    || $data['payload']['lines'] === [] || $data['payload']['trips'] === []) {
                    throw new RuntimeException('The portal package is incomplete or does not match the requested period and fleet.');
                }
                foreach ($data['payload']['lines'] as $line) {
                    if (($line['type'] ?? null) === 'flying' && ($line['pairings'] ?? []) === []) {
                        throw new RuntimeException('The portal package has a flying line without a pairing.');
                    }
                    foreach ($line['pairings'] ?? [] as $pairing) {
                        if (! isset($data['payload']['trips'][$pairing['id']])) {
                            throw new RuntimeException('The portal package has a missing linked trip.');
                        }
                    }
                }

                return DB::transaction(function () use ($data, $period, $fleet, $owner): string {
                    $currentOwner = User::query()->find($owner->id);
                    if ($currentOwner === null) {
                        throw new AuthorizationException('The configured bid admin is unavailable.');
                    }
                    Gate::forUser($currentOwner)->authorize('manage-crew-bids');
                    if (BidPackage::query()->where('fingerprint', $data['fingerprint'])->exists()) {
                        return 'unchanged';
                    }

                    $hasPackage = BidPackage::query()->where('period', $period)->where('fleet', $fleet)->exists();
                    $package = BidPackage::query()->firstOrCreate(['fingerprint' => $data['fingerprint']], $data + [
                        'imported_by' => $owner->id,
                        'published_at' => $hasPackage ? null : now(),
                    ]);

                    if (! $package->wasRecentlyCreated) {
                        return 'unchanged';
                    }

                    return $hasPackage ? 'revision_held' : 'published';
                });
            } finally {
                $this->downloader->cleanup($files);
            }
        });
    }
}
