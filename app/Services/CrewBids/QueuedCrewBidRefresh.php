<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use App\Jobs\RefreshCrewBidSnapshot;
use App\Models\BidPackage;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use SensitiveParameter;
use Throwable;

/**
 * @phpstan-type RefreshState array{id: string, user_id: int, package_id: int, seat: string, format: ?string, state: string, message: string, deadline: int, lock_key: string, lock_owner: string, package_hash: string}
 */
class QueuedCrewBidRefresh
{
    public const LIFETIME = 900;

    public function start(User $user, BidPackage $package, string $seat, #[SensitiveParameter] string $employee, #[SensitiveParameter] string $password, ?string $format): void
    {
        $lockKey = 'crew-bid-refresh-active:'.$package->period.':'.$package->fleet.':'.$seat;
        $lock = Cache::lock($lockKey, self::LIFETIME);
        if (! $lock->get()) {
            throw new BidPortalException('A shared refresh is already queued or running for this position. Try again shortly.');
        }
        $id = (string) Str::uuid();
        try {
            Cache::put($this->key($id), [
                'id' => $id, 'user_id' => $user->id, 'package_id' => $package->id, 'seat' => $seat, 'format' => $format,
                'state' => 'queued', 'message' => 'Shared-bid refresh queued. You can keep using the app.',
                'deadline' => now()->timestamp + 600,
                'lock_key' => $lockKey, 'lock_owner' => $lock->owner(), 'package_hash' => $this->packageHash($package),
            ], self::LIFETIME);
            Cache::put($this->key($id).':credentials', Crypt::encryptString(json_encode(['employee' => $employee, 'password' => $password], JSON_THROW_ON_ERROR)), 600);
            Cache::put($this->latestKey($user, $package, $seat), $id, self::LIFETIME);
            Bus::dispatch(new RefreshCrewBidSnapshot($id, $lockKey, $lock->owner()));
        } catch (Throwable $exception) {
            Cache::forget($this->key($id).':credentials');
            Cache::forget($this->key($id));
            Cache::forget($this->latestKey($user, $package, $seat));
            $lock->release();
            throw $exception;
        }
    }

    /** @return RefreshState|null */
    public function current(User $user, BidPackage $package, string $seat): ?array
    {
        $id = Cache::get($this->latestKey($user, $package, $seat));

        return is_string($id) ? $this->owned($id, $user, $package) : null;
    }

    /** @return RefreshState|null */
    public function owned(string $id, User $user, BidPackage $package): ?array
    {
        $state = $this->state($id);
        if ($state === null || $state['user_id'] !== $user->id || $state['package_id'] !== $package->id) {
            return null;
        }
        if (in_array($state['state'], ['queued', 'running'], true) && $state['package_hash'] === '') {
            $this->fail($id, 'This refresh was queued before an app update. Enter your portal login again to retry.');

            return $this->state($id);
        }
        if (in_array($state['state'], ['queued', 'running'], true) && $state['deadline'] <= now()->timestamp) {
            $this->fail($id, 'The refresh expired before it could finish. Enter your portal login again to retry.');

            return $this->state($id);
        }

        return $state;
    }

    public function run(string $id, SyncCrewBidSnapshot $sync): void
    {
        $state = $this->state($id);
        if ($state === null || $state['state'] !== 'queued') {
            Cache::forget($this->key($id).':credentials');

            return;
        }
        try {
            $secret = Cache::pull($this->key($id).':credentials');
            if ($state['package_hash'] === '') {
                throw new BidPortalException('This refresh was queued before an app update. Enter your portal login again to retry.');
            }
            if (! is_string($secret) || $state['deadline'] <= now()->timestamp) {
                throw new BidPortalException('The queued login expired. Enter your portal login again to retry.');
            }
            $user = User::query()->find($state['user_id']);
            $package = BidPackage::query()->find($state['package_id']);
            if ($user === null || ! $user->canUseCrewBids() || $package === null || $package->published_at === null
                || ($package->archived_at !== null && ! $package->workspaces()->where('user_id', $user->id)->exists())) {
                throw new BidPortalException('This bid package is no longer available to your account.');
            }
            if (! hash_equals($state['package_hash'], $this->packageHash($package))) {
                throw new BidPortalException('This bid package changed while the refresh was queued. Open it again and retry.');
            }
            /** @var array{employee: string, password: string} $credentials */
            $credentials = json_decode(Crypt::decryptString($secret), true, flags: JSON_THROW_ON_ERROR);
            $state['deadline'] = now()->timestamp + 270;
            $this->write($state, 'running', 'Reading and validating the complete Kalitta report…');
            $result = $sync->handle($package, $credentials['employee'], $credentials['password'], $state['seat'], $state['format']);
            if ($result['download'] !== null) {
                Cache::put($this->key($id).':download', Crypt::encryptString($result['download']), self::LIFETIME);
            }
            $this->write($state, 'completed', 'Shared bids updated for all K4 Bids users. View the updated prediction below.');
        } catch (BidPortalException $exception) {
            $this->fail($id, $exception->getMessage());
        } catch (Throwable) {
            $this->fail($id, 'The shared-bid refresh could not finish. Previous snapshot data remains available. Try again.');
        } finally {
            Cache::forget($this->key($id).':credentials');
        }
    }

    public function fail(string $id, string $message): void
    {
        Cache::forget($this->key($id).':credentials');
        $state = $this->state($id);
        if ($state !== null && in_array($state['state'], ['queued', 'running'], true)) {
            $this->write($state, 'failed', $message);
            if ($state['lock_key'] !== '' && $state['lock_owner'] !== '') {
                Cache::restoreLock($state['lock_key'], $state['lock_owner'])->release();
            }
        }
    }

    public function download(string $id): ?string
    {
        $download = Cache::get($this->key($id).':download');

        return is_string($download) ? Crypt::decryptString($download) : null;
    }

    /** @return RefreshState|null */
    private function state(string $id): ?array
    {
        $state = Cache::get($this->key($id));

        return is_array($state) ? $state + ['lock_key' => '', 'lock_owner' => '', 'package_hash' => ''] : null;
    }

    /** @param RefreshState $state */
    private function write(array $state, string $status, string $message): void
    {
        $state['state'] = $status;
        $state['message'] = $message;
        Cache::put($this->key($state['id']), $state, self::LIFETIME);
    }

    private function key(string $id): string
    {
        return 'crew-bid-refresh:'.$id;
    }

    private function latestKey(User $user, BidPackage $package, string $seat): string
    {
        return 'crew-bid-refresh-latest:'.$user->id.':'.$package->id.':'.$seat;
    }

    private function packageHash(BidPackage $package): string
    {
        return hash('sha256', json_encode([$package->period, $package->fleet, $package->source_revision, $package->fingerprint, $package->payload], JSON_THROW_ON_ERROR));
    }
}
