<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultCache;
use Tests\TestCase;

class FlightPlanResultCacheTest extends TestCase
{
    public function test_it_stores_results_by_opaque_user_scoped_key_and_forgets_them(): void
    {
        $cache = app(FlightPlanResultCache::class);
        $owner = User::factory()->make(['id' => 123]);
        $otherUser = User::factory()->make(['id' => 456]);

        $resultKey = $cache->put($owner, ['route' => 'DCT TEST']);

        $this->assertTrue(str($resultKey)->isUlid());
        $this->assertSame(['route' => 'DCT TEST'], $cache->get($owner, $resultKey));
        $this->assertNull($cache->get($otherUser, $resultKey));
        $this->assertNull($cache->get($owner, 'not-a-valid-result-key'));

        $cache->forget($owner, $resultKey);

        $this->assertNull($cache->get($owner, $resultKey));
    }
}
