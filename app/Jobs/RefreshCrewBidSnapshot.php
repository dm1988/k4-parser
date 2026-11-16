<?php

namespace App\Jobs;

use App\Services\CrewBids\QueuedCrewBidRefresh;
use App\Services\CrewBids\SyncCrewBidSnapshot;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RefreshCrewBidSnapshot implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public function __construct(public string $refreshId, public string $lockKey, public string $lockOwner)
    {
        $this->onConnection('crew-bid-refreshes');
        $this->onQueue('crew-bid-refreshes');
    }

    public function handle(QueuedCrewBidRefresh $refreshes, SyncCrewBidSnapshot $sync): void
    {
        try {
            $refreshes->run($this->refreshId, $sync);
        } finally {
            Cache::restoreLock($this->lockKey, $this->lockOwner)->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(QueuedCrewBidRefresh::class)->fail($this->refreshId, 'The refresh worker stopped before completion. Enter your portal login again to retry.');
        Cache::restoreLock($this->lockKey, $this->lockOwner)->release();
    }
}
