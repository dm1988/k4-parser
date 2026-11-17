<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/** @phpstan-type OperationBinding array{user_id: int, epoch: string, session_id: string, nonce: string, lease: ?string} */
class CrewBidOperationGuard
{
    /** @var OperationBinding|null */
    private ?array $binding = null;

    private int $deadline = 0;

    /** @return OperationBinding */
    public function begin(User $user, Session $session, ?string $lease = null): array
    {
        if (config('session.driver') === 'cookie') {
            throw new BidPortalException('K4 operations require server-side app sessions.');
        }
        if (! $session->has('crew_bid_secret_nonce')) {
            $session->put('crew_bid_secret_nonce', Str::random(40));
        }
        $session->put('crew_bid_secret_owner', $user->id);
        $this->assertNotRevoked($session->getId(), $session->get('crew_bid_secret_nonce'));
        $session->save();
        $binding = ['user_id' => $user->id, 'epoch' => $user->crew_bid_credential_epoch, 'session_id' => $session->getId(), 'nonce' => $session->get('crew_bid_secret_nonce'), 'lease' => $lease];
        $this->activate($binding);

        return $binding;
    }

    /** @param OperationBinding $binding */
    public function activate(array $binding): void
    {
        $this->binding = $binding;
        $this->deadline = now()->timestamp + 270;
    }

    /** @return OperationBinding */
    public function binding(): array
    {
        if ($this->binding === null) {
            throw new BidPortalException('Start this K4 operation from an authenticated app session.');
        }
        $this->assertAuthorized();

        return $this->binding;
    }

    public function clear(): void
    {
        $this->binding = null;
        $this->deadline = 0;
    }

    public function assertAuthorized(): void
    {
        if ($this->binding === null) {
            return;
        }
        $binding = $this->binding;
        if ($this->deadline <= now()->timestamp) {
            throw new BidPortalException('The K4 operation expired. Start a new operation.');
        }
        $this->assertNotRevoked($binding['session_id'], $binding['nonce']);
        $user = User::query()->find($binding['user_id']);
        if ($user === null || ! $user->canUseCrewBids() || ! hash_equals($user->crew_bid_credential_epoch, $binding['epoch'])) {
            throw new BidPortalException('K4 access changed. Unlock or enter your login again.');
        }
        $reader = clone app('session')->driver();
        $reader->flush();
        $reader->setId($binding['session_id']);
        $reader->start();
        $valid = $reader->get('crew_bid_secret_owner') === $user->id && $reader->get('crew_bid_secret_nonce') === $binding['nonce'];
        if (! $valid) {
            throw new BidPortalException('The app session was locked or ended. Unlock or enter your login again.');
        }
        if ($binding['lease'] !== null) {
            app(CrewBidVaultService::class)->lease($user, $binding['session_id'], $binding['lease']);
        }
    }

    public static function revocationKey(string $sessionId, string $nonce): string
    {
        return 'revoked:'.hash('sha256', $sessionId.':'.$nonce);
    }

    private function assertNotRevoked(string $sessionId, string $nonce): void
    {
        if (app(CrewBidSecretStore::class)->get(self::revocationKey($sessionId, $nonce)) !== null) {
            throw new BidPortalException('The app session was locked or ended. Unlock or enter your login again.');
        }
    }
}
