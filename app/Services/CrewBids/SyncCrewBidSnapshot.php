<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use App\Models\BidPackage;
use App\Models\CrewBidSnapshot;
use Illuminate\Support\Facades\Cache;
use SensitiveParameter;

class SyncCrewBidSnapshot
{
    public function __construct(private ViewEmployeeBids $portal) {}

    /** @return array{snapshot: CrewBidSnapshot, download: ?string} */
    public function handle(BidPackage $package, #[SensitiveParameter] string $employee, #[SensitiveParameter] string $password, string $seat, ?string $format = null): array
    {
        $lock = Cache::lock('crew-bid-snapshot:'.$package->period.':'.$package->fleet.':'.$seat, 300);
        if (! $lock->get()) {
            throw new BidPortalException('A shared refresh is already running for this position. Try again shortly.');
        }
        try {
            $result = $this->portal->all($package, $employee, $password, $seat, $format);
            $snapshot = CrewBidSnapshot::query()->updateOrCreate(['period' => $package->period, 'fleet' => $package->fleet, 'seat' => $seat], [
                'portal_package_id' => $result['context']['package_id'], 'portal_revision' => $result['context']['package_revision'],
                'retrieved_at' => $result['retrieved_at'], 'bidders' => $result['bidders'],
            ]);

            return ['snapshot' => $snapshot, 'download' => $result['download']];
        } finally {
            $lock->release();
        }
    }
}
