<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use App\Jobs\RefreshSubmittedBids;
use App\Models\BidPackage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SensitiveParameter;
use Throwable;

/** @phpstan-type PersonalRefreshState array{id: string, user_id: int, package_id: int, seat: string, state: string, message: string, deadline: int, lock_key: string, lock_owner: string, package_hash: string, started_at: string, finished_at: ?string} */
class QueuedSubmittedBidRefresh
{
    public const LIFETIME = 900;

    public function __construct(private CrewBidSecretStore $secrets, private CrewBidOperationGuard $guard, private StoreSubmittedBidSnapshot $snapshots) {}

    public function start(User $user, BidPackage $package, string $seat, #[SensitiveParameter] string $employee, #[SensitiveParameter] string $password): string
    {
        $this->snapshots->authorize($user, $package, $seat);
        $lockKey = 'submitted-bid-active:'.$user->id.':'.$package->id.':'.$seat;
        $lock = Cache::lock($lockKey, self::LIFETIME);
        if (! $lock->get()) {
            throw new BidPortalException('A personal K4 check is already queued or running.');
        }
        $id = (string) Str::ulid();
        $state = ['id' => $id, 'user_id' => $user->id, 'package_id' => $package->id, 'seat' => $seat,
            'state' => 'queued', 'message' => 'K4 check queued. My bid list is unchanged.', 'deadline' => now()->timestamp + 600,
            'lock_key' => $lockKey, 'lock_owner' => $lock->owner(), 'package_hash' => $this->snapshots->packageHash($package),
            'started_at' => CarbonImmutable::now()->toISOString(), 'finished_at' => null];
        try {
            Cache::put($this->key($id), $state, self::LIFETIME);
            $this->secrets->put('submitted:'.$id, ['employee' => $employee, 'password' => $password,
                'binding' => $this->guard->binding(), 'context' => $this->context($state)], 600);
            Cache::put($this->latestKey($user, $package, $seat), $id, self::LIFETIME);
            Bus::dispatch(new RefreshSubmittedBids($id, $lockKey, $lock->owner()));
        } catch (Throwable $exception) {
            $this->discard($id);
            Cache::forget($this->key($id));
            $lock->release();
            throw $exception;
        }

        return $id;
    }

    /** @return PersonalRefreshState|null */
    public function current(User $user, BidPackage $package, string $seat): ?array
    {
        $id = Cache::get($this->latestKey($user, $package, $seat));

        return is_string($id) ? $this->owned($id, $user, $package, $seat) : null;
    }

    /** @return PersonalRefreshState|null */
    public function owned(string $id, User $user, BidPackage $package, string $seat): ?array
    {
        $state = $this->state($id);
        if ($state === null || $state['user_id'] !== $user->id || $state['package_id'] !== $package->id || $state['seat'] !== $seat) {
            return null;
        }
        if (in_array($state['state'], ['queued', 'running'], true) && $state['deadline'] <= now()->timestamp) {
            $this->fail($id, 'The K4 check expired. Enter or unlock your login to try again.');

            return $this->state($id);
        }

        return $state;
    }

    public function run(string $id, ReadPersonalSubmittedBids $reader): void
    {
        $state = $this->state($id);
        if ($state === null || $state['state'] !== 'queued') {
            return;
        }
        try {
            $secret = $this->secrets->pull('submitted:'.$id);
            if ($secret === null || $state['deadline'] <= now()->timestamp) {
                throw new BidPortalException('The queued K4 login expired. Enter or unlock your login to try again.');
            }
            $user = User::query()->find($state['user_id']);
            $package = BidPackage::query()->find($state['package_id']);
            if ($user === null || ! $user->canUseCrewBids() || $package === null) {
                throw new BidPortalException('This comparison is no longer available to your account.');
            }
            $this->snapshots->authorize($user, $package, $state['seat']);
            if (($secret['context'] ?? null) !== $this->context($state) || ! is_array($secret['binding'] ?? null)
                || ($secret['binding']['user_id'] ?? null) !== $user->id || ! is_string($secret['employee'] ?? null) || ! is_string($secret['password'] ?? null)
                || ! hash_equals($state['package_hash'], $this->snapshots->packageHash($package))) {
                throw new BidPortalException('The queued K4 comparison context changed. Start a fresh check.');
            }
            $this->guard->activate($secret['binding']);
            $this->guard->assertAuthorized();
            $state['deadline'] = now()->timestamp + 270;
            $this->write($state, 'running', 'Reading your submitted K4 bids…');
            $attemptId = $this->snapshots->attemptId($user, $package, $state['seat']);
            $report = $reader->read($secret['employee'], $secret['password']);
            $checkedAt = CarbonImmutable::now();
            $this->guard->assertAuthorized();
            $latest = Cache::get($this->latestKey($user, $package, $state['seat']));
            if ($latest !== $id || ($this->state($id)['state'] ?? null) !== 'running') {
                throw new BidPortalException('This K4 check was superseded or expired. The latest result is unchanged.');
            }
            $this->snapshots->store($user, $package, $state['seat'], $secret['employee'], $report, $id,
                CarbonImmutable::parse($state['started_at']), $checkedAt, $state['package_hash'], attemptId: $attemptId);
            $this->write($state, 'completed', 'K4 submitted bids checked. My bid list is unchanged.');
        } catch (BidPortalException $exception) {
            $this->fail($id, $exception->getMessage());
        } catch (Throwable) {
            $this->fail($id, 'The K4 check could not finish. The last successful check and My bid list are unchanged.');
        } finally {
            unset($secret);
            $this->guard->clear();
            $this->discard($id);
        }
    }

    public function fail(string $id, string $message): void
    {
        $this->discard($id);
        $state = $this->state($id);
        if ($state !== null && in_array($state['state'], ['queued', 'running'], true)) {
            $this->write($state, 'failed', $message);
            Cache::restoreLock($state['lock_key'], $state['lock_owner'])->release();
        }
    }

    /** @return PersonalRefreshState|null */
    private function state(string $id): ?array
    {
        $state = Cache::get($this->key($id));

        return is_array($state) ? $state : null;
    }

    /** @param PersonalRefreshState $state */
    private function write(array $state, string $status, string $message): void
    {
        $state['state'] = $status;
        $state['message'] = $message;
        $state['finished_at'] = in_array($status, ['completed', 'failed'], true) ? now()->utc()->format('Y-m-d H:i:s').' UTC' : null;
        Cache::put($this->key($state['id']), $state, self::LIFETIME);
    }

    private function key(string $id): string
    {
        return 'submitted-bid-refresh:'.$id;
    }

    private function latestKey(User $user, BidPackage $package, string $seat): string
    {
        return 'submitted-bid-latest:'.$user->id.':'.$package->id.':'.$seat;
    }

    /** @param PersonalRefreshState $state */
    private function context(array $state): string
    {
        return hash('sha256', json_encode(['personal-submitted-bids', $state['id'], $state['user_id'], $state['package_id'], $state['seat'], $state['package_hash'], $state['started_at']], JSON_THROW_ON_ERROR));
    }

    private function discard(string $id): void
    {
        try {
            $this->secrets->forget('submitted:'.$id);
        } catch (BidPortalException) {
            // Grants in an unavailable store still expire after ten minutes.
        }
    }
}
