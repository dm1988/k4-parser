<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use App\Models\CrewBidCredentialVault;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SensitiveParameter;

class CrewBidVaultService
{
    public ?int $loginRehashOwner = null;

    private ?int $rewrappingOwner = null;

    public function __construct(private CrewBidSecretStore $secrets, private CrewBidVaultCrypto $crypto) {}

    public function connect(User $user, Session $session, #[SensitiveParameter] string $password, #[SensitiveParameter] string $employee, #[SensitiveParameter] string $portalPassword): void
    {
        $this->enabled();
        $user = $this->verifyPassword($user, $password);
        $this->checkEmployee($user, $employee);
        $this->secrets->available();
        app(CrewBidOperationGuard::class)->begin($user, $session);
        app(BidPortalSession::class)->viewEmployees($employee, $portalPassword, function (string $html) use ($employee): array {
            if (! app(ReadBidPortalPage::class)->context($html, $employee)['account_matches_input']) {
                throw new BidPortalException('The authenticated portal account does not match the supplied employee number.');
            }

            return [];
        });
        $id = (string) Str::ulid();
        $envelope = $this->crypto->seal($id, $user->id, $password, $employee, $portalPassword);
        DB::transaction(function () use ($user, $password, $employee, $id, $envelope): void {
            $current = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->verifyPassword($current, $password);
            if ($current->crew_bid_credential_epoch !== $user->crew_bid_credential_epoch) {
                throw new BidPortalException('Your account changed while connecting. Try again.');
            }
            $this->checkEmployee($current, $employee);
            CrewBidCredentialVault::query()->where('user_id', $user->id)->delete();
            (new CrewBidCredentialVault)->forceFill(['id' => $id, 'user_id' => $user->id, 'envelope' => $envelope, 'account_hint' => '••'.substr($employee, -2)])->save();
            $current->forceFill(['crew_bid_credential_epoch' => Str::random(32)])->save();
        });
        $this->lock($session);
        $this->unlock($user->fresh(), $session, $password);
    }

    public function unlock(User $user, Session $session, #[SensitiveParameter] string $password): void
    {
        $this->enabled();
        $user = $this->verifyPassword($user, $password);
        $vault = $this->vault($user);
        $key = $this->crypto->unlock($vault->id, $user->id, $vault->envelope, $password);
        try {
            $credentials = $this->crypto->credentials($vault->id, $user->id, $vault->envelope, $key);
            $this->checkEmployee($user, $credentials['employee']);
            unset($credentials);
            $this->lock($session);
            $handle = Str::random(64);
            $absolute = min((int) config('crew_bids.vault.absolute_seconds'), (int) config('session.lifetime') * 60);
            $ttl = min((int) config('crew_bids.vault.idle_seconds'), $absolute);
            $this->secrets->put('lease:'.$handle, [
                'user_id' => $user->id, 'session_id' => $session->getId(), 'epoch' => $user->crew_bid_credential_epoch,
                'vault_id' => $vault->id, 'fingerprint' => $this->fingerprint($vault), 'key' => base64_encode($key),
                'absolute' => now()->timestamp + $absolute, 'idle' => now()->timestamp + $ttl,
            ], $ttl);
            $session->put('crew_bid_vault_handle', $handle);
        } finally {
            sodium_memzero($key);
        }
    }

    /** @return array<string, mixed> */
    public function lease(User $user, string $sessionId, string $handle): array
    {
        $this->enabled();
        $user = $user->fresh();
        $lease = $this->secrets->get('lease:'.$handle);
        $vault = $this->vault($user);
        if (! $user->canUseCrewBids() || $lease === null || ($lease['user_id'] ?? null) !== $user->id
            || ($lease['session_id'] ?? null) !== $sessionId || ($lease['epoch'] ?? null) !== $user->crew_bid_credential_epoch
            || ($lease['vault_id'] ?? null) !== $vault->id || ($lease['fingerprint'] ?? null) !== $this->fingerprint($vault)
            || ($lease['absolute'] ?? 0) <= now()->timestamp || ($lease['idle'] ?? 0) <= now()->timestamp) {
            throw new BidPortalException('Unlock your K4 connection to continue.');
        }

        return $lease;
    }

    /** @return array{employee: string, password: string} */
    public function credentials(User $user, Session $session): array
    {
        $handle = $session->get('crew_bid_vault_handle');
        if (! is_string($handle)) {
            throw new BidPortalException('Unlock your K4 connection to continue.');
        }
        $lease = $this->lease($user, $session->getId(), $handle);
        $after = $lease;
        $ttl = min((int) config('crew_bids.vault.idle_seconds'), $lease['absolute'] - now()->timestamp);
        $after['idle'] = now()->timestamp + $ttl;
        if (! $this->secrets->replace('lease:'.$handle, $lease, $after, $ttl)) {
            throw new BidPortalException('The K4 connection was locked. Unlock it again to continue.');
        }
        $vault = $this->vault($user);
        $key = base64_decode($lease['key'], true);
        if ($key === false || strlen($key) !== 32) {
            throw new BidPortalException('Unlock your K4 connection to continue.');
        }
        try {
            $credentials = $this->crypto->credentials($vault->id, $user->id, $vault->envelope, $key);
            $this->checkEmployee($user->fresh(), $credentials['employee']);

            return $credentials;
        } finally {
            sodium_memzero($key);
        }
    }

    /** @return array{connected: bool, unlocked: bool, hint: ?string} */
    public function status(User $user, Session $session): array
    {
        $vault = CrewBidCredentialVault::query()->where('user_id', $user->id)->first();
        $unlocked = false;
        $handle = $session->get('crew_bid_vault_handle');
        if (config('crew_bids.vault.enabled') && $vault !== null && is_string($handle)) {
            try {
                $this->lease($user, $session->getId(), $handle);
                $unlocked = true;
            } catch (BidPortalException) {
                $unlocked = false;
            }
        }

        return ['connected' => $vault !== null, 'unlocked' => $unlocked, 'hint' => $vault?->account_hint];
    }

    public function lock(Session $session, ?int $owner = null): void
    {
        $handle = $session->pull('crew_bid_vault_handle');
        $nonce = $session->get('crew_bid_secret_nonce');
        $session->put('crew_bid_secret_nonce', Str::random(40));
        if (is_string($handle) || is_string($nonce)) {
            try {
                if (is_string($nonce)) {
                    $this->secrets->put(CrewBidOperationGuard::revocationKey($session->getId(), $nonce), ['revoked' => true], max(7200, (int) config('session.lifetime') * 60 + 600));
                }
                if (is_string($handle)) {
                    $this->secrets->forget('lease:'.$handle);
                }
            } catch (BidPortalException) {
                $owner ??= $session->get('crew_bid_secret_owner', auth()->id());
                if (is_int($owner)) {
                    User::query()->whereKey($owner)->update(['crew_bid_credential_epoch' => Str::random(32)]);
                }
            }
        }
    }

    public function forget(User $user, Session $session): void
    {
        DB::transaction(function () use ($user): void {
            $current = User::query()->lockForUpdate()->findOrFail($user->id);
            CrewBidCredentialVault::query()->where('user_id', $user->id)->delete();
            $current->forceFill(['crew_bid_credential_epoch' => Str::random(32)])->save();
        });
        $this->lock($session);
    }

    public function changePassword(User $user, #[SensitiveParameter] string $oldPassword, #[SensitiveParameter] string $newPassword): void
    {
        DB::transaction(function () use ($user, $oldPassword, $newPassword): void {
            $current = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! Hash::check($oldPassword, $current->password)) {
                throw new BidPortalException('Your app password changed. Try again.');
            }
            $vault = CrewBidCredentialVault::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($vault !== null) {
                $vault->forceFill(['envelope' => $this->crypto->rewrap($vault->id, $user->id, $vault->envelope, $oldPassword, $newPassword)])->save();
            }
            $this->rewrappingOwner = $current->id;
            try {
                $current->forceFill(['password' => Hash::make($newPassword)])->save();
            } finally {
                $this->rewrappingOwner = null;
            }
        });
    }

    private function enabled(): void
    {
        if (! config('crew_bids.vault.enabled')) {
            throw new BidPortalException('Saved K4 connections are disabled. Enter a one-off login instead.');
        }
    }

    public function preservesPasswordVault(User $user): bool
    {
        return $this->rewrappingOwner === $user->id
            || ($this->loginRehashOwner === $user->id && Hash::needsRehash($user->getOriginal('password')));
    }

    private function verifyPassword(User $user, #[SensitiveParameter] string $password): User
    {
        $current = $user->fresh();
        if (! $current->canUseCrewBids() || ! Hash::check($password, $current->password)) {
            throw new BidPortalException('Your app password could not be verified.');
        }

        return $current;
    }

    private function vault(User $user): CrewBidCredentialVault
    {
        $vault = CrewBidCredentialVault::query()->where('user_id', $user->id)->first();
        if ($vault === null) {
            throw new BidPortalException('Connect your K4 account to continue.');
        }

        return $vault;
    }

    private function checkEmployee(User $user, #[SensitiveParameter] string $employee): void
    {
        if ($user->crew_bid_employee_verified_at !== null && $user->crew_bid_employee_number !== $employee) {
            throw new BidPortalException('Use the K4 account matching your verified employee number.');
        }
    }

    private function fingerprint(CrewBidCredentialVault $vault): string
    {
        return hash('sha256', json_encode($vault->envelope, JSON_THROW_ON_ERROR));
    }
}
