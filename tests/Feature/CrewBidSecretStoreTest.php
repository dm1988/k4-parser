<?php

namespace Tests\Feature;

use App\Exceptions\BidPortalException;
use App\Services\CrewBids\CrewBidSecretStore;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\RequiresEnvironmentVariable;
use PHPUnit\Framework\Attributes\TestWith;
use Redis;
use RedisException;
use Tests\TestCase;

class CrewBidSecretStoreTest extends TestCase
{
    #[RequiresEnvironmentVariable('CREW_BIDS_SECRETS_INTEGRATION', '1')]
    public function test_dedicated_store_supports_atomic_claim_and_does_not_recreate_a_revoked_lease(): void
    {
        $store = new CrewBidSecretStore;
        $key = 'test:'.Str::random(32);
        $redis = new Redis;
        $redis->connect('crew-bid-secrets', 6379, 2.0);
        $this->assertSame(['save' => ''], $redis->config('GET', 'save'));
        $this->assertSame(['appendonly' => 'no'], $redis->config('GET', 'appendonly'));
        $store->available();
        try {
            $store->put($key, ['secret' => 'synthetic-only'], 30);
            $this->assertSame(['secret' => 'synthetic-only'], $store->get($key));
            $this->assertTrue($store->replace($key, ['secret' => 'synthetic-only'], ['secret' => 'changed'], 30));
            $this->assertFalse($store->replace($key, ['secret' => 'synthetic-only'], ['secret' => 'stale'], 30));
            $this->assertSame(['secret' => 'changed'], $store->pull($key));
            $this->assertNull($store->pull($key));
            $this->assertFalse($store->replace($key, ['secret' => 'changed'], ['secret' => 'resurrected'], 30));
        } finally {
            $store->forget($key);
        }
    }

    #[RequiresEnvironmentVariable('CREW_BIDS_SECRETS_INTEGRATION', '1')]
    #[TestWith(['SAVE'])]
    #[TestWith(['BGSAVE'])]
    #[TestWith(['CONFIG', 'SET', 'save', ''])]
    #[TestWith(['GET', 'outside-credential-namespace'])]
    public function test_local_application_acl_denies_snapshots_reconfiguration_and_unscoped_keys(string ...$command): void
    {
        $redis = new Redis;
        $redis->connect('crew-bid-secrets', 6379, 2.0);

        $this->expectException(RedisException::class);
        $this->expectExceptionMessage('NOPERM');
        $redis->rawCommand(...$command);
    }

    #[RequiresEnvironmentVariable('CREW_BIDS_SECRETS_INTEGRATION', '1')]
    public function test_the_ordinary_snapshotting_redis_is_rejected_as_a_secret_backend(): void
    {
        config(['crew_bids.secrets.host' => 'redis']);

        $this->expectException(BidPortalException::class);
        $this->expectExceptionMessage('secure K4 memory store is unavailable');
        (new CrewBidSecretStore)->put('test:'.Str::random(32), ['secret' => 'synthetic-only'], 30);
    }

    public function test_nonlocal_environments_require_explicit_host_attestation_authentication_and_tls(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['crew_bids.secrets.runtime_attested' => false]);

        $this->expectException(BidPortalException::class);
        (new CrewBidSecretStore)->available();
    }
}
