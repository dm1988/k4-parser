<?php

namespace App\Jobs;

use App\Services\CrewBids\QueuedSubmittedBidRefresh;
use App\Services\CrewBids\ReadPersonalSubmittedBids;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RefreshSubmittedBids implements ShouldBeEncrypted, ShouldQueue
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

    public function handle(QueuedSubmittedBidRefresh $refreshes, ReadPersonalSubmittedBids $reader): void
    {
        try {
            $refreshes->run($this->refreshId, $reader);
        } finally {
            Cache::restoreLock($this->lockKey, $this->lockOwner)->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(QueuedSubmittedBidRefresh::class)->fail($this->refreshId, 'The K4 check worker stopped. Enter or unlock your login to try again.');
        Cache::restoreLock($this->lockKey, $this->lockOwner)->release();
    }
}
