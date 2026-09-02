<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class FlightPlanResultCacheTest extends TestCase
{
    public function test_it_stores_results_by_opaque_user_scoped_versioned_key(): void
    {
        $cache = app(FlightPlanResultCache::class);
        $owner = User::factory()->make(['id' => 123]);
        $flightPlan = ['flight_plan_data' => ['route' => ['departure' => 'PANC']]];

        $resultKey = $cache->put($owner, $flightPlan);

        $this->assertTrue(str($resultKey)->isUlid());
        $this->assertTrue(Cache::has("flight_plan_results:v2:123:{$resultKey}"));
        $this->assertSame($flightPlan, $cache->get($owner, $resultKey));
    }

    public function test_it_expires_results_after_the_configured_ttl(): void
    {
        config()->set('cache.extracted_results_ttl', 1);

        $cache = app(FlightPlanResultCache::class);
        $owner = User::factory()->make(['id' => 123]);
        $resultKey = $cache->put($owner, ['flight_plan_data' => []]);

        $this->travel(61)->seconds();

        $this->assertNull($cache->get($owner, $resultKey));
    }

    public function test_it_rejects_results_owned_by_another_user(): void
    {
        $cache = app(FlightPlanResultCache::class);
        $owner = User::factory()->make(['id' => 123]);
        $otherUser = User::factory()->make(['id' => 456]);
        $resultKey = $cache->put($owner, ['flight_plan_data' => []]);

        $this->assertNull($cache->get($otherUser, $resultKey));
        $this->assertSame(['flight_plan_data' => []], $cache->get($owner, $resultKey));
    }

    public function test_it_rejects_malformed_result_keys(): void
    {
        $cache = app(FlightPlanResultCache::class);
        $owner = User::factory()->make(['id' => 123]);

        $this->assertNull($cache->get($owner, 'not-a-valid-result-key'));
    }

    public function test_it_forgets_a_result(): void
    {
        $cache = app(FlightPlanResultCache::class);
        $owner = User::factory()->make(['id' => 123]);
        $resultKey = $cache->put($owner, ['flight_plan_data' => []]);

        $cache->forget($owner, $resultKey);

        $this->assertNull($cache->get($owner, $resultKey));
    }

    public function test_it_rejects_pre_cutover_results_from_the_unversioned_namespace(): void
    {
        $cache = app(FlightPlanResultCache::class);
        $owner = User::factory()->make(['id' => 123]);
        $resultKey = (string) Str::ulid();

        Cache::put("flight_plan_results:123:{$resultKey}", [
            'departure' => 'PANC',
            'destination' => 'KMIA',
            'flight_plan_data' => ['route' => ['departure' => 'PANC']],
        ], now()->addHour());

        $this->assertTrue(Cache::has("flight_plan_results:123:{$resultKey}"));
        $this->assertNull($cache->get($owner, $resultKey));
    }
}
